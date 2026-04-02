<?php

namespace App\Services;

use App\Models\Post;
use App\Models\TagCorpus;

class TfIdfCalculator
{
    public function calculate(int $tenantId, array $terms): array
    {
        if (empty($terms)) return [];

        // TF: term frequency in this document
        $termCounts = array_count_values($terms);
        $maxCount = max($termCounts);
        $tf = [];
        foreach ($termCounts as $term => $count) {
            $tf[$term] = 0.5 + 0.5 * ($count / $maxCount);
        }

        // Total documents for this tenant
        $totalDocs = max(1, Post::where('tenant_id', $tenantId)
            ->whereIn('status', ['draft', 'approved', 'published'])
            ->count());

        // DF: document frequency from corpus
        $uniqueTerms = array_unique($terms);
        $dfMap = TagCorpus::where('tenant_id', $tenantId)
            ->whereIn('term', $uniqueTerms)
            ->pluck('document_frequency', 'term')
            ->all();

        // TF-IDF
        $scores = [];
        foreach ($uniqueTerms as $term) {
            $df = $dfMap[$term] ?? 0;
            $idf = log(($totalDocs + 1) / ($df + 1)) + 1;
            $scores[$term] = ($tf[$term] ?? 0) * $idf;
        }

        arsort($scores);
        return $scores;
    }

    public function updateCorpus(int $tenantId, array $uniqueTerms): void
    {
        foreach ($uniqueTerms as $term) {
            TagCorpus::updateOrCreate(
                ['tenant_id' => $tenantId, 'term' => $term],
                ['document_frequency' => \DB::raw('document_frequency + 1')]
            );
        }
    }
}
