<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator\Findings;

use LlmDispatch\Runner\Evaluator\Findings\Finding;
use LlmDispatch\Runner\Evaluator\Findings\FindingsMatcher;
use LlmDispatch\Runner\Evaluator\Findings\GroundTruth;
use PHPUnit\Framework\TestCase;

final class FindingsMatcherTest extends TestCase
{
    public function testMatchesWithinLineWindow(): void
    {
        $gt = $this->groundTruth([$this->defect('d1', 'src/A.php', 'sqli', 100)]);
        $out = (new FindingsMatcher())->match([new Finding('src/A.php', 115, 'sqli', 'x')], $gt);
        $this->assertSame(1, $out->truePositives);
        $this->assertSame([], $out->unmatched);
        $this->assertSame(['d1'], $out->matchedDefectIds);
    }

    public function testRejectsOutsideLineWindowWithoutSpan(): void
    {
        $gt = $this->groundTruth([$this->defect('d1', 'src/A.php', 'sqli', 100)]);
        $out = (new FindingsMatcher())->match([new Finding('src/A.php', 116, 'sqli', 'x')], $gt);
        $this->assertSame(0, $out->truePositives);
        $this->assertCount(1, $out->unmatched);
        $this->assertSame(['d1'], $out->missedDefectIds);
    }

    public function testMatchesInsideFunctionSpanBeyondWindow(): void
    {
        $gt = $this->groundTruth([$this->defect('d1', 'src/A.php', 'race_condition', 100, 60, 140)]);
        $out = (new FindingsMatcher())->match([new Finding('src/A.php', 135, 'race_condition', 'x')], $gt);
        $this->assertSame(1, $out->truePositives);
    }

    public function testClassMismatchDoesNotMatch(): void
    {
        $gt = $this->groundTruth([$this->defect('d1', 'src/A.php', 'sqli', 100)]);
        $out = (new FindingsMatcher())->match([new Finding('src/A.php', 100, 'xss', 'x')], $gt);
        $this->assertSame(0, $out->truePositives);
    }

    public function testSecondFindingOnSameDefectIsDuplicate(): void
    {
        $gt = $this->groundTruth([$this->defect('d1', 'src/A.php', 'sqli', 100)]);
        $out = (new FindingsMatcher())->match([
            new Finding('src/A.php', 100, 'sqli', 'a'),
            new Finding('src/A.php', 101, 'sqli', 'b'),
        ], $gt);
        $this->assertSame(1, $out->truePositives);
        $this->assertSame(1, $out->duplicates);
        $this->assertSame([], $out->unmatched);
    }

    public function testPrefersUnclaimedDefectWhenClaimedOneAlsoFits(): void
    {
        $gt = $this->groundTruth([
            $this->defect('d1', 'src/A.php', 'sqli', 100),
            $this->defect('d2', 'src/A.php', 'sqli', 110),
        ]);
        $out = (new FindingsMatcher())->match([
            new Finding('src/A.php', 100, 'sqli', 'a'),
            new Finding('src/A.php', 110, 'sqli', 'b'),
        ], $gt);
        $this->assertSame(2, $out->truePositives);
        $this->assertSame(0, $out->duplicates);
        $this->assertSame(['d1', 'd2'], $out->matchedDefectIds);
        $this->assertSame([], $out->missedDefectIds);
    }

    public function testDuplicateWhenAllFittingDefectsClaimed(): void
    {
        $gt = $this->groundTruth([$this->defect('d1', 'src/A.php', 'sqli', 100)]);
        $out = (new FindingsMatcher())->match([
            new Finding('src/A.php', 100, 'sqli', 'a'),
            new Finding('src/A.php', 101, 'sqli', 'b'),
        ], $gt);
        $this->assertSame(1, $out->truePositives);
        $this->assertSame(1, $out->duplicates);
        $this->assertSame([], $out->unmatched);
        $this->assertSame(['d1'], $out->matchedDefectIds);
        $this->assertSame([], $out->missedDefectIds);
    }

    public function testRecallAgainstTotalDefectCount(): void
    {
        $gt = $this->groundTruth([
            $this->defect('d1', 'src/A.php', 'sqli', 100),
            $this->defect('d2', 'src/B.php', 'xss', 10),
        ]);
        $out = (new FindingsMatcher())->match([new Finding('src/A.php', 100, 'sqli', 'x')], $gt);
        $this->assertEqualsWithDelta(0.5, $out->recall(2), 0.001);
    }

    public function testMatchesAcrossMockProjectPrefixConventions(): void
    {
        $gt = $this->groundTruth([$this->defect('d1', 'mock-project/src/A.php', 'sqli', 100)]);
        $out = (new FindingsMatcher())->match([new Finding('src/A.php', 100, 'sqli', 'x')], $gt);
        $this->assertSame(1, $out->truePositives);
    }

    /** @param list<array<string, mixed>> $defects */
    private function groundTruth(array $defects): GroundTruth
    {
        $path = sys_get_temp_dir() . '/gt-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, json_encode(['defects' => $defects], JSON_THROW_ON_ERROR));
        return GroundTruth::fromFile($path);
    }

    /** @return array<string, mixed> */
    private function defect(string $id, string $file, string $class, int $line, ?int $s = null, ?int $e = null): array
    {
        return ['id' => $id, 'file' => $file, 'defect_class' => $class, 'line' => $line,
            'span_start' => $s, 'span_end' => $e, 'detectability_proof' => 'p'];
    }
}
