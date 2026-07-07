<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Analysis;

/**
 * Detects if a model transcript accessed forbidden answer-key paths.
 *
 * DESIGN: Contamination is detected ONLY by the presence of forbidden markers
 * as case-sensitive substrings. Filesystem-escape heuristics (e.g., grep -r /, find /opt)
 * were removed in favor of marker-only detection because:
 *
 * 1. Greedy problem: The escape regexes used [^\n]* which, on stream-JSON transcript lines
 *    containing both a legitimate workspace command AND CLI metadata, would match across
 *    the JSON boundary (e.g., a line with both "grep -r pattern /private/tmp/.../src" and
 *    the CLI's "/Users/anders/.claude/..." metadata would falsely match escape:grep-host).
 *
 * 2. Sufficient condition: A genuine answer-key access ALWAYS puts the forbidden path
 *    into the transcript—either via the command itself or in find/ls output. The forbidden
 *    markers ('/opt/homebrew/var/www/cc/llm-dispatch-experiment/tasks' and 'tasks/ground-truth')
 *    are specific enough that they never appear in CLI metadata or normal workspace operations.
 *
 * Contamination occurs when the transcript contains any forbidden marker as a case-sensitive substring.
 */
final class ContaminationDetector
{
    /**
     * @param list<string> $forbiddenMarkers Case-sensitive substrings to detect
     */
    public function __construct(private readonly array $forbiddenMarkers) {}

    private const EVIDENCE_MAX_LEN = 300;

    /**
     * Scan a transcript for contamination.
     *
     * @return array{contaminated: bool, matches: list<string>, evidence: list<string>}
     *   contaminated: true if any forbidden marker is found as a case-sensitive substring
     *   matches: deduplicated list of matched marker strings
     *   evidence: deduplicated list of the actual transcript lines that contained a marker,
     *     each truncated to self::EVIDENCE_MAX_LEN chars (with a trailing "…" if truncated)
     */
    public function scan(string $transcript): array
    {
        $matches = [];

        // Check for forbidden markers as case-sensitive substrings
        foreach ($this->forbiddenMarkers as $marker) {
            if (str_contains($transcript, $marker)) {
                $matches[] = $marker;
            }
        }

        // Deduplicate matches
        $matches = array_unique($matches);
        $matches = array_values($matches); // Reindex after unique

        $evidence = $this->collectEvidence($transcript);

        return [
            'contaminated' => !empty($matches),
            'matches' => $matches,
            'evidence' => $evidence,
        ];
    }

    /**
     * @return list<string>
     */
    private function collectEvidence(string $transcript): array
    {
        $evidence = [];

        foreach (explode("\n", $transcript) as $line) {
            if ($line === '') {
                continue;
            }

            // Check if this line contains any forbidden marker
            foreach ($this->forbiddenMarkers as $marker) {
                if (str_contains($line, $marker)) {
                    $evidence[] = $this->truncate($line);
                    break; // Move to next line after finding first marker
                }
            }
        }

        return array_values(array_unique($evidence));
    }

    private function truncate(string $line): string
    {
        if (mb_strlen($line) <= self::EVIDENCE_MAX_LEN) {
            return $line;
        }

        return mb_substr($line, 0, self::EVIDENCE_MAX_LEN) . '…';
    }
}
