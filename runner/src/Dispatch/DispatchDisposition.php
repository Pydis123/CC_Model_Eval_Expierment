<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Dispatch;

final class DispatchDisposition
{
    public const COMPLETED = 'completed';
    public const REFUSED = 'refused_in_band';
    public const REROUTED = 'model_rerouted';
    public const ERROR = 'error';

    private const REFUSAL_PATTERNS = [
        "/\bi can(?:'|no|')?t help\b/i",
        '/\bi (?:cannot|can not) (?:help|assist|comply)\b/i',
        "/\bi(?:'m| am) (?:unable|not able) to\b/i",
        '/\bi (?:must|have to) decline\b/i',
        '/\bcannot assist with (?:this|that)\b/i',
    ];

    public static function classify(string $expectedModelId, RunOutcome $outcome): string
    {
        if ($outcome->finalOutcome === 'error') {
            return self::ERROR;
        }

        foreach ($outcome->iterations as $it) {
            if ($it->modelIdReported !== '' && $it->modelIdReported !== $expectedModelId) {
                return self::REROUTED;
            }
        }

        $last = $outcome->iterations[count($outcome->iterations) - 1] ?? null;
        if ($last !== null && self::looksLikeRefusal($last->resultText)) {
            return self::REFUSED;
        }

        return self::COMPLETED;
    }

    public static function looksLikeRefusal(string $text): bool
    {
        foreach (self::REFUSAL_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }
        return false;
    }
}
