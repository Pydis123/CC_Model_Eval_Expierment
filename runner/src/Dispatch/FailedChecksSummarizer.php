<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Dispatch;

use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Evaluator\EvaluationResult;

final class FailedChecksSummarizer
{
    public function summarize(EvaluationResult $eval): string
    {
        $lines = [];
        foreach ($eval->checks as $check) {
            if ($check->passed) {
                continue;
            }
            $lines[] = sprintf('- %s: %s', $check->type, $this->messageFor($check));
        }
        return implode("\n", $lines);
    }

    private function messageFor(CheckResult $check): string
    {
        if (isset($check->details['error']) && is_string($check->details['error'])) {
            return $check->details['error'];
        }
        return 'check failed';
    }
}
