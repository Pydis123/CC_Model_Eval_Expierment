<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Analysis;

/**
 * Detects if a model transcript accessed forbidden markers or attempted filesystem escapes.
 *
 * Contamination occurs when the transcript contains:
 * 1. Any forbidden marker as a case-sensitive substring, OR
 * 2. A filesystem-escape pattern (find/grep over root or host directories)
 */
final class ContaminationDetector
{
    /**
     * @param list<string> $forbiddenMarkers Case-sensitive substrings to detect
     */
    public function __construct(private readonly array $forbiddenMarkers) {}

    /**
     * Scan a transcript for contamination.
     *
     * @return array{contaminated: bool, matches: list<string>}
     *   contaminated: true if any marker or escape pattern is found
     *   matches: deduplicated list of matched marker strings or escape labels
     */
    public function scan(string $transcript): array
    {
        $matches = [];

        // Check for forbidden markers
        foreach ($this->forbiddenMarkers as $marker) {
            if (str_contains($transcript, $marker)) {
                $matches[] = $marker;
            }
        }

        // Check for escape patterns
        // find / or find /root-level-dirs
        if (preg_match('/\bfind\s+\/(?:\s|$)/', $transcript)) {
            $matches[] = 'escape:find-root';
        }

        // find /opt, /Users, /home, /private, /var
        if (preg_match('/\bfind\s+\/(opt|Users|home|private|var)\b/', $transcript)) {
            $matches[] = 'escape:find-hostdir';
        }

        // grep -r or grep -rn from root or host dirs
        if (preg_match('/\bgrep\b[^\n]*\br[^\n]*\s\/(?:\s|opt|Users|home|$)/', $transcript)) {
            $matches[] = 'escape:grep-host';
        }

        // Deduplicate matches
        $matches = array_unique($matches);
        $matches = array_values($matches); // Reindex after unique

        return [
            'contaminated' => !empty($matches),
            'matches' => $matches,
        ];
    }
}
