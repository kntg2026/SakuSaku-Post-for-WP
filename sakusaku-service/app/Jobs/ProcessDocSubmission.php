<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostImage;
use App\Services\DocsHtmlConverter;
use App\Services\GoogleDocsService;
use App\Services\ImageProcessingService;
use App\Services\NotificationService;
use App\Services\ProcessedImage;
use App\Services\TagGeneratorService;
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
        TagGeneratorService $tagGenerator,
        NotificationService $notifications,
    ): void {
        $tenantContext->set($this->post->tenant);

        try {
            $this->post->update(['status' => PostStatus::Processing]);

            // 1. Fetch Google Doc HTML
            Log::info("ProcessDoc [{$this->post->id}]: Fetching doc {$this->post->google_doc_id}");
            $docsResult = $docs->fetchDocument($this->post->google_doc_id, $this->post->user);

            if (empty(trim($docsResult->html))) {
                throw new \RuntimeException('Google Doc is empty');
            }

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
                // For data URIs, don't store the full base64 in original_url
                $originalUrl = str_starts_with($imgData['url'], 'data:')
                    ? 'data:image (embedded in doc)'
                    : $imgData['url'];

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
                        'original_url' => $originalUrl,
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
                        'original_url' => $originalUrl,
                        'sort_order' => $imgData['position'],
                        'status' => 'failed',
                        'error_message' => substr($e->getMessage(), 0, 500),
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

            // 6. Replace any remaining image placeholders with fallback text
            $htmlContent = preg_replace(
                '/<!-- sakusaku-image:\d+ -->/',
                '<p><em>[画像を処理できませんでした]</em></p>',
                $htmlContent
            );

            // Update WP post with final HTML
            Log::info("ProcessDoc [{$this->post->id}]: Updating WP post with final HTML");
            $wpBridge->updatePost($wpResult['wp_post_id'], [
                'content' => $htmlContent,
            ]);
            $this->post->update(['html_content' => $htmlContent]);

            // 7. Set featured image
            if ($featuredAttachmentId) {
                $wpBridge->setFeaturedImage($wpResult['wp_post_id'], $featuredAttachmentId);
            }

            // 8. Generate tags via MeCab + TF-IDF
            try {
                Log::info("ProcessDoc [{$this->post->id}]: Generating tags");
                $tags = $tagGenerator->generate($this->post);
                // Sync tags to WP
                foreach (array_keys($tags) as $tagName) {
                    try {
                        $wpBridge->createTag($tagName, $wpResult['wp_post_id']);
                    } catch (\Throwable $e) {
                        Log::warning("ProcessDoc [{$this->post->id}]: WP tag sync failed for '{$tagName}': {$e->getMessage()}");
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("ProcessDoc [{$this->post->id}]: Tag generation failed: {$e->getMessage()}");
            }

            // 9. Update status to draft
            $this->post->update(['status' => PostStatus::Draft]);
            Log::info("ProcessDoc [{$this->post->id}]: Complete. WP post ID: {$wpResult['wp_post_id']}");

            // 10. Send notification
            try {
                $notifications->send($this->post->tenant, 'on_submit', [
                    'title' => $this->post->title,
                    'poster' => $this->post->user->name,
                    'category' => $this->post->category?->name ?? '—',
                ]);
            } catch (\Throwable $e) {
                Log::warning("ProcessDoc [{$this->post->id}]: Notification failed: {$e->getMessage()}");
            }

        } catch (\Throwable $e) {
            $attempt = $this->attempts();
            $maxTries = $this->tries;
            $isFinal = $attempt >= $maxTries;

            Log::error("ProcessDoc [{$this->post->id}]: Failed (attempt {$attempt}/{$maxTries}): {$e->getMessage()}");

            $this->post->update([
                'status' => PostStatus::Failed,
                'admin_comment' => $isFinal
                    ? "Processing failed after {$attempt} attempts: {$e->getMessage()}"
                    : "Processing failed (retry {$attempt}/{$maxTries}): {$e->getMessage()}",
            ]);

            throw $e;
        }
    }
}
