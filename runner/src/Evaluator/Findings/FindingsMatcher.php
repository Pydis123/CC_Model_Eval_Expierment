<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator\Findings;

final class FindingsMatcher
{
    /**
     * @param list<Finding> $findings
     */
    public function match(array $findings, GroundTruth $gt): MatchOutcome
    {
        $claimed = [];
        $tp = 0;
        $dup = 0;
        $unmatched = [];

        foreach ($findings as $finding) {
            // First pass: find the first unclaimed defect that fits
            $unclaimedHit = null;
            foreach ($gt->defects as $defect) {
                if (!$this->fits($finding, $defect)) {
                    continue;
                }
                if (!isset($claimed[$defect->id])) {
                    $unclaimedHit = $defect;
                    break;
                }
            }

            if ($unclaimedHit !== null) {
                $claimed[$unclaimedHit->id] = true;
                $tp++;
            } else {
                // Second pass: check if any claimed defect fits
                $claimedHit = false;
                foreach ($gt->defects as $defect) {
                    if ($this->fits($finding, $defect) && isset($claimed[$defect->id])) {
                        $claimedHit = true;
                        break;
                    }
                }

                if ($claimedHit) {
                    $dup++;
                } else {
                    $unmatched[] = $finding;
                }
            }
        }

        $missed = [];
        foreach ($gt->defects as $defect) {
            if (!isset($claimed[$defect->id])) {
                $missed[] = $defect->id;
            }
        }

        return new MatchOutcome($tp, $dup, $unmatched, $missed, array_keys($claimed));
    }

    private function fits(Finding $f, SeededDefect $d): bool
    {
        if (self::normalize($f->file) !== self::normalize($d->file) || $f->defectClass !== $d->defectClass) {
            return false;
        }

        if (abs($f->line - $d->line) <= 15) {
            return true;
        }

        return $d->spanStart !== null && $d->spanEnd !== null
            && $f->line >= $d->spanStart && $f->line <= $d->spanEnd;
    }

    private static function normalize(string $file): string
    {
        return str_starts_with($file, 'mock-project/') ? substr($file, strlen('mock-project/')) : $file;
    }
}
