<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostImage;
use App\Services\DocsHtmlConverter;
use App\Services\GoogleDocsService;
use App\Services\ImageProcessingService;
use App\Services\ProcessedImage;
use App\Services\TenantContext;
use App\Services\WpBridgeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDocSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(private Post $post) {}

    public function handle(
        TenantContext $tenantContext,
        GoogleDocsService $docs,
        DocsHtmlConverter $converter,
        ImageProcessingService $imageService,
        WpBridgeService $wpBridge,
    ): void {
        $tenantContext->set($this->post->tenant);

        try {
            $this->post->update(['status' => PostStatus::Processing]);

            // 1. Fetch Google Doc HTML
            Log::info("ProcessDoc [{$this->post->id}]: Fetching doc {$this->post->google_doc_id}");
            $docsResult = $docs->fetchDocument($this->post->google_doc_id, $this->post->user);

            // 2. Convert HTML
            Log::info("ProcessDoc [{$this->post->id}]: Converting HTML");
            $conversionResult = $converter->convert($docsResult->html);

            $title = $conversionResult->title ?: $this->post->title ?: 'Untitled';
            $this->post->update([
                'title' => $title,
                'html_content' => $conversionResult->cleanHtml,
            ]);

            // 3. Process images
            $processedImages = [];
            foreach ($conversionResult->images as $imgData) {
                try {
                    Log::info("ProcessDoc [{$this->post->id}]: Processing image {$imgData['position']}");
                    $processed = $imageService->process(
                        $imgData['url'],
                        $this->post->tenant_id
                    );
                    $processedImages[$imgData['position']] = $processed;

                    PostImage::create([
                        'post_id' => $this->post->id,
                        'tenant_id' => $this->post->tenant_id,
                        'original_url' => $imgData['url'],
                        'stored_path' => $processed->tempPath,
                        'width' => $processed->width,
                        'height' => $processed->height,
                        'file_size' => $processed->fileSize,
                        'mime_type' => $processed->mimeType,
                        'is_featured' => $imgData['position'] === 0,
                        'sort_order' => $imgData['position'],
                        'status' => 'processing',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("ProcessDoc [{$this->post->id}]: Image {$imgData['position']} failed: {$e->getMessage()}");
                    PostImage::create([
                        'post_id' => $this->post->id,
                        'tenant_id' => $this->post->tenant_id,
                        'original_url' => $imgData['url'],
                        'sort_order' => $imgData['position'],
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }

            // 4. Create WP draft (with placeholder images)
            Log::info("ProcessDoc [{$this->post->id}]: Creating WP draft");
            $wpResult = $wpBridge->createDraft([
                'title' => $title,
                'content' => $conversionResult->cleanHtml,
                'categories' => $this->post->category_id
                    ? [$this->post->category->wp_category_id]
                    : [],
                'excerpt' => $this->post->excerpt ?? '',
                'meta' => [
                    '_sakusaku_post_id' => $this->post->id,
                    '_sakusaku_tenant_id' => $this->post->tenant_id,
                    '_sakusaku_doc_url' => $this->post->google_doc_url,
                ],
            ]);

            $this->post->update([
                'wp_post_id' => $wpResult['wp_post_id'],
                'wp_preview_url' => $wpResult['preview_url'] ?? null,
            ]);

            // 5. Upload images to WP and replace placeholders
            $htmlContent = $conversionResult->cleanHtml;
            $featuredAttachmentId = null;

            foreach ($processedImages as $position => $processed) {
                try {
                    Log::info("ProcessDoc [{$this->post->id}]: Uploading image {$position} to WP");
                    $uploadResult = $wpBridge->uploadImage([
                        'data' => $imageService->toBase64($processed->tempPath),
                        'filename' => $processed->filename,
                        'alt_text' => $conversionResult->images[$position]['alt'] ?? '',
                        'post_id' => $wpResult['wp_post_id'],
                    ]);

                    $postImage = PostImage::where('post_id', $this->post->id)
                        ->where('sort_order', $position)
                        ->first();

                    if ($postImage) {
                        $postImage->update([
                            'wp_attachment_id' => $uploadResult['attachment_id'],
                            'wp_url' => $uploadResult['url'],
                            'status' => 'uploaded',
                        ]);
                    }

                    // Replace placeholder in HTML
                    $imgTag = sprintf(
                        '<img src="%s" alt="%s" width="%s" height="%s" />',
                        $uploadResult['url'],
                        htmlspecialchars($conversionResult->images[$position]['alt'] ?? ''),
                        $uploadResult['width'] ?? '',
                        $uploadResult['height'] ?? ''
                    );
                    $htmlContent = str_replace(
                        "<!-- sakusaku-image:{$position} -->",
                        $imgTag,
                        $htmlContent
                    );

                    if ($position === 0) {
                        $featuredAttachmentId = $uploadResult['attachment_id'];
                    }
                } catch (\Throwable $e) {
                    Log::warning("ProcessDoc [{$this->post->id}]: WP upload for image {$position} failed: {$e->getMessage()}");
                }
            }

            // 6. Update WP post with final HTML
            if (!empty($processedImages)) {
                Log::info("ProcessDoc [{$this->post->id}]: Updating WP post with final HTML");
                $wpBridge->updatePost($wpResult['wp_post_id'], [
                    'content' => $htmlContent,
                ]);

                $this->post->update(['html_content' => $htmlContent]);
            }

            // 7. Set featured image
            if ($featuredAttachmentId) {
                $wpBridge->setFeaturedImage($wpResult['wp_post_id'], $featuredAttachmentId);
            }

            // 8. Update status to draft
            $this->post->update(['status' => PostStatus::Draft]);
            Log::info("ProcessDoc [{$this->post->id}]: Complete. WP post ID: {$wpResult['wp_post_id']}");

        } catch (\Throwable $e) {
            Log::error("ProcessDoc [{$this->post->id}]: Failed: {$e->getMessage()}");
            $this->post->update([
                'status' => PostStatus::Failed,
                'admin_comment' => "Processing failed: {$e->getMessage()}",
            ]);
            throw $e;
        }
    }
}
