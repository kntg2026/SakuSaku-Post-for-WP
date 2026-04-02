<?php

namespace App\Services;

class MeCabTokenizer
{
    private string $mecabPath;

    public function __construct()
    {
        $this->mecabPath = env('MECAB_PATH', '/usr/bin/mecab');
    }

    public function tokenize(string $text): array
    {
        $process = proc_open(
            [$this->mecabPath],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start MeCab process');
        }

        fwrite($pipes[0], $text);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $this->parseOutput($output);
    }

    private function parseOutput(string $output): array
    {
        $tokens = [];
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if ($line === 'EOS' || $line === '') continue;

            $parts = explode("\t", $line, 2);
            if (count($parts) < 2) continue;

            $surface = $parts[0];
            $features = explode(',', $parts[1]);

            $tokens[] = [
                'surface' => $surface,
                'pos' => $features[0] ?? '',
                'pos1' => $features[1] ?? '',
                'pos2' => $features[2] ?? '',
                'base' => $features[6] ?? $surface,
            ];
        }

        return $tokens;
    }

    public function extractNouns(string $text): array
    {
        $excluded = ['非自立', '接尾', '数', '代名詞'];

        return collect($this->tokenize($text))
            ->filter(fn($t) =>
                $t['pos'] === '名詞' && !in_array($t['pos1'], $excluded)
            )
            ->pluck('base')
            ->filter(fn($w) => mb_strlen($w) >= 2)
            ->unique()
            ->values()
            ->all();
    }
}
