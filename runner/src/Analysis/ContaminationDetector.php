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

    private const EVIDENCE_MAX_LEN = 300;

    /**
     * Scan a transcript for contamination.
     *
     * @return array{contaminated: bool, matches: list<string>, evidence: list<string>}
     *   contaminated: true if any marker or escape pattern is found
     *   matches: deduplicated list of matched marker strings or escape labels
     *   evidence: deduplicated list of the actual transcript lines that triggered a match,
     *     each truncated to self::EVIDENCE_MAX_LEN chars (with a trailing "…" if truncated)
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
        // find / or find /root-level-dirs (with optional flags like -H, -L)
        $findRootPattern = '/\bfind\s+(?:-[A-Za-z]+\s+)*\/(?:\s|$)/';
        if (preg_match($findRootPattern, $transcript)) {
            $matches[] = 'escape:find-root';
        }

        // find /opt, /Users, /home (with optional flags)
        // Host-dir list is deliberately scoped to plausible repo-checkout roots,
        // NOT all host dirs, because the run workspace lives under /private/tmp
        // and must not self-flag legitimate workspace commands.
        $findHostdirPattern = '/\bfind\s+(?:-[A-Za-z]+\s+)*\/(opt|Users|home)\b/';
        if (preg_match($findHostdirPattern, $transcript)) {
            $matches[] = 'escape:find-hostdir';
        }

        // grep with recursive flag (-r or -R, possibly bundled like -rn) targeting root or host dirs
        // (excluding /private and /var per the host-dir scoping rules)
        $grepHostPattern = '/\bgrep\s+[^\n]*-[A-Za-z]*[rR][A-Za-z]*\s+[^\n]*\/(?:\s|opt|Users|home|$)/';
        if (preg_match($grepHostPattern, $transcript)) {
            $matches[] = 'escape:grep-host';
        }

        // Deduplicate matches
        $matches = array_unique($matches);
        $matches = array_values($matches); // Reindex after unique

        $escapePatterns = [$findRootPattern, $findHostdirPattern, $grepHostPattern];
        $evidence = $this->collectEvidence($transcript, $this->forbiddenMarkers, $escapePatterns);

        return [
            'contaminated' => !empty($matches),
            'matches' => $matches,
            'evidence' => $evidence,
        ];
    }

    /**
     * @param list<string> $markers
     * @param list<string> $escapePatterns
     * @return list<string>
     */
    private function collectEvidence(string $transcript, array $markers, array $escapePatterns): array
    {
        $evidence = [];

        foreach (explode("\n", $transcript) as $line) {
            if ($line === '') {
                continue;
            }

            $isEvidence = false;
            foreach ($markers as $marker) {
                if (str_contains($line, $marker)) {
                    $isEvidence = true;
                    break;
                }
            }
            if (!$isEvidence) {
                foreach ($escapePatterns as $pattern) {
                    if (preg_match($pattern, $line)) {
                        $isEvidence = true;
                        break;
                    }
                }
            }

            if ($isEvidence) {
                $evidence[] = $this->truncate($line);
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
