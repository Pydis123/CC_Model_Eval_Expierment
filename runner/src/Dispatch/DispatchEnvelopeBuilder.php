<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Dispatch;

final class DispatchEnvelopeBuilder
{
    public function build(string $rawPrompt, ?string $priorFailedChecksSummary): string
    {
        if ($priorFailedChecksSummary === null) {
            return $rawPrompt;
        }

        return sprintf(
            "%s\n\n---\nPrevious attempt failed the evaluator with these issues:\n%s\n\nFix the failing checks.",
            $rawPrompt,
            $priorFailedChecksSummary,
        );
    }
}
