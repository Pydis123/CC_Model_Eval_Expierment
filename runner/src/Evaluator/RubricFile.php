<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator;

final class RubricFile
{
    /**
     * @return list<array{id: string, text: string, anchors: array{0: string, 1: string, 2: string}}>
     */
    public static function loadCriteria(string $path): array
    {
        $raw = @file_get_contents($path);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($decoded) || !isset($decoded['criteria']) || !is_array($decoded['criteria'])) {
            return [];
        }

        $criteria = [];
        foreach ($decoded['criteria'] as $entry) {
            if (!is_array($entry) || !is_string($entry['id'] ?? null)) {
                continue;
            }
            $anchors = is_array($entry['anchors'] ?? null) ? $entry['anchors'] : [];
            $criteria[] = [
                'id' => $entry['id'],
                'text' => is_string($entry['text'] ?? null) ? $entry['text'] : '',
                'anchors' => [
                    0 => is_string($anchors[0] ?? null) ? $anchors[0] : '',
                    1 => is_string($anchors[1] ?? null) ? $anchors[1] : '',
                    2 => is_string($anchors[2] ?? null) ? $anchors[2] : '',
                ],
            ];
        }

        return $criteria;
    }
}
