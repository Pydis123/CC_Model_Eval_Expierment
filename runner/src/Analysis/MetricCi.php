<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Analysis;

use InvalidArgumentException;

final class MetricCi
{
    /**
     * @param list<float> $values
     * @return array{low: float, high: float}
     */
    public static function bootstrap(array $values, int $samples, int $seed): array
    {
        if ($values === []) {
            throw new InvalidArgumentException('MetricCi::bootstrap requires at least one value');
        }
        if ($samples < 1) {
            throw new InvalidArgumentException('MetricCi::bootstrap requires samples >= 1');
        }

        mt_srand($seed);

        $n = count($values);
        $resampleMeans = [];
        for ($i = 0; $i < $samples; $i++) {
            $sum = 0.0;
            for ($j = 0; $j < $n; $j++) {
                $index = mt_rand(0, $n - 1);
                $sum += $values[$index];
            }
            $resampleMeans[] = $sum / $n;
        }

        sort($resampleMeans);

        $low = $resampleMeans[(int) floor(0.025 * ($samples - 1))];
        $high = $resampleMeans[(int) floor(0.975 * ($samples - 1))];

        return ['low' => $low, 'high' => $high];
    }
}
