<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostTag;

class TagGeneratorService
{
    public function __construct(
        private MeCabTokenizer $tokenizer,
        private TfIdfCalculator $tfidf,
    ) {}

    public function generate(Post $post, int $maxTags = 10, float $minScore = 0.1): array
    {
        $text = strip_tags($post->html_content ?? '');
        if (empty(trim($text))) return [];

        $nouns = $this->tokenizer->extractNouns($text);
        if (empty($nouns)) return [];

        $scores = $this->tfidf->calculate($post->tenant_id, $nouns);

        // Take top N above threshold
        $tags = [];
        foreach ($scores as $term => $score) {
            if ($score < $minScore) break;
            if (count($tags) >= $maxTags) break;
            $tags[$term] = $score;
        }

        // Save tags
        PostTag::where('post_id', $post->id)->delete();

        foreach ($tags as $tagName => $score) {
            PostTag::create([
                'post_id' => $post->id,
                'tenant_id' => $post->tenant_id,
                'tag_name' => $tagName,
                'score' => round($score, 4),
            ]);
        }

        // Update corpus
        $this->tfidf->updateCorpus($post->tenant_id, array_unique($nouns));

        return $tags;
    }
}
