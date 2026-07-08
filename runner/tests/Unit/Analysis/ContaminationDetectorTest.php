<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Analysis;

use LlmDispatch\Runner\Analysis\ContaminationDetector;
use PHPUnit\Framework\TestCase;

final class ContaminationDetectorTest extends TestCase
{
    public function testFlagsAbsoluteRepoTasksPath(): void
    {
        $markers = [
            '/opt/homebrew/var/www/cc/llm-dispatch-experiment/tasks',
            'tasks/ground-truth',
        ];
        $detector = new ContaminationDetector($markers);

        $transcript = 'cat /opt/homebrew/var/www/cc/llm-dispatch-experiment/tasks/ground-truth/102-security-audit.json';
        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertContains('/opt/homebrew/var/www/cc/llm-dispatch-experiment/tasks', $result['matches']);
        $this->assertContains($transcript, $result['evidence']);
    }

    public function testFlagsRelativeGroundTruthFragment(): void
    {
        $markers = [
            '/opt/homebrew/var/www/cc/llm-dispatch-experiment/tasks',
            'tasks/ground-truth',
        ];
        $detector = new ContaminationDetector($markers);

        $transcript = 'find . -path \'*tasks/ground-truth*\'';
        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertContains('tasks/ground-truth', $result['matches']);
        $this->assertContains($transcript, $result['evidence']);
    }

    /**
     * Critical regression guard: a single stream-JSON line containing BOTH a legitimate
     * workspace grep AND the CLI metadata path must NOT be flagged as contaminated.
     * This was the root cause of false positives with the greedy regex approach.
     */
    public function testDoesNotFlagInWorkspaceGrepWithCliMetadata(): void
    {
        $markers = [
            '/opt/homebrew/var/www/cc/llm-dispatch-experiment/tasks',
            'tasks/ground-truth',
        ];
        $detector = new ContaminationDetector($markers);

        // Simulates a realistic stream-JSON line: legitimate workspace grep
        // plus CLI metadata path on the same line, as seen in real transcripts.
        $transcript = '{"type":"assistant","message":{"content":[{"type":"tool_use","name":"Bash","input":{"command":"grep -r \"public function post\" /private/tmp/llm-disp-abc123/mock-project/src"}}]},"session_path":"/Users/USERNAME/.claude/projects/-private-tmp-llm-disp-abc123"}';
        $result = $detector->scan($transcript);

        $this->assertFalse($result['contaminated'], 'Workspace grep with CLI metadata should not be flagged');
        $this->assertEmpty($result['matches']);
        $this->assertEmpty($result['evidence']);
    }

    public function testFlagsFindEnumerationOutputContainingKeyPath(): void
    {
        $markers = [
            '/opt/homebrew/var/www/cc/llm-dispatch-experiment/tasks',
            'tasks/ground-truth',
        ];
        $detector = new ContaminationDetector($markers);

        // Simulates output from find / that prints the ground-truth path
        $transcript = <<<'EOT'
/opt/homebrew/var/www/cc/llm-dispatch-experiment/tasks/ground-truth/102-security-audit.json
/opt/homebrew/var/www/cc/llm-dispatch-experiment/tasks/ground-truth/103-feature-flag.json
EOT;
        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertContains('/opt/homebrew/var/www/cc/llm-dispatch-experiment/tasks', $result['matches']);
    }

    public function testCleanNormalAuditTranscript(): void
    {
        $markers = [
            '/opt/homebrew/var/www/cc/llm-dispatch-experiment/tasks',
            'tasks/ground-truth',
        ];
        $detector = new ContaminationDetector($markers);

        $transcript = <<<'EOT'
ls -R src
cat src/Domain/Repository/TicketRepository.php
grep -rn "query(" src
git diff baseline..review-target
EOT;
        $result = $detector->scan($transcript);

        $this->assertFalse($result['contaminated']);
        $this->assertEmpty($result['matches']);
        $this->assertEmpty($result['evidence']);
    }

    public function testEmptyTranscriptIsClean(): void
    {
        $markers = ['/opt/homebrew/var/www/cc/llm-dispatch-experiment/tasks'];
        $detector = new ContaminationDetector($markers);

        $result = $detector->scan('');

        $this->assertFalse($result['contaminated']);
        $this->assertEmpty($result['matches']);
        $this->assertEmpty($result['evidence']);
    }

    public function testEvidenceCapturesMatchingLineOnly(): void
    {
        $markers = ['tasks/ground-truth'];
        $detector = new ContaminationDetector($markers);

        $cleanLine = 'ls -R src';
        $offendingLine = 'cat /opt/homebrew/var/www/cc/llm-dispatch-experiment/tasks/ground-truth/102.json';
        $transcript = $cleanLine . "\n" . $offendingLine;

        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertContains($offendingLine, $result['evidence']);
        $this->assertNotContains($cleanLine, $result['evidence']);
        // Verify exactly one evidence line (the one with the marker)
        $this->assertCount(1, $result['evidence']);
    }

    public function testMatchesDeduplicated(): void
    {
        $markers = ['tasks/ground-truth'];
        $detector = new ContaminationDetector($markers);

        $transcript = <<<'EOT'
cat tasks/ground-truth/102-security-audit.json
find . -path tasks/ground-truth/103-*.json
ls tasks/ground-truth/
EOT;
        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        // The marker should appear exactly once even though it's in the transcript multiple times
        $this->assertSame(1, count($result['matches']));
        $this->assertContains('tasks/ground-truth', $result['matches']);
        // Each distinct offending line is captured as separate evidence.
        $this->assertCount(3, $result['evidence']);
    }

    public function testNormalGrepInWorkdirIsClean(): void
    {
        $markers = [];
        $detector = new ContaminationDetector($markers);

        $transcript = 'grep -rn "pattern" src';
        $result = $detector->scan($transcript);

        $this->assertFalse($result['contaminated']);
        $this->assertEmpty($result['matches']);
    }

    public function testNormalFindInWorkdirIsClean(): void
    {
        $markers = [];
        $detector = new ContaminationDetector($markers);

        $transcript = 'find . -name "*.php"';
        $result = $detector->scan($transcript);

        $this->assertFalse($result['contaminated']);
        $this->assertEmpty($result['matches']);
    }

    public function testDoesNotFlagOwnWorktreeUnderPrivateTmp(): void
    {
        $markers = [
            '/opt/homebrew/var/www/cc/llm-dispatch-experiment/tasks',
            'tasks/ground-truth',
        ];
        $detector = new ContaminationDetector($markers);

        // The run workspace lives under /private/tmp on macOS and is legitimate.
        $transcript = 'find /private/tmp/llm-disp-42/mock-project -name \'*.php\'';
        $result = $detector->scan($transcript);

        $this->assertFalse($result['contaminated']);
        $this->assertEmpty($result['matches']);
    }

    public function testGrepWithinWorkspaceIsClean(): void
    {
        $markers = [];
        $detector = new ContaminationDetector($markers);

        // grep with recursive flag but targeting local directory (no forbidden markers) is clean
        $transcript = 'grep -rn "query(" src';
        $result = $detector->scan($transcript);

        $this->assertFalse($result['contaminated']);
        $this->assertEmpty($result['matches']);
    }

    public function testEvidenceLinesAreTruncatedAndDeduplicated(): void
    {
        $markers = ['forbidden-marker'];
        $detector = new ContaminationDetector($markers);

        $longLine = 'cat forbidden-marker/' . str_repeat('x', 400) . '.json';
        $transcript = $longLine . "\n" . $longLine;

        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertCount(1, $result['evidence'], 'identical offending lines are deduplicated');
        // Truncated to 300 chars, plus a trailing ellipsis marker.
        $this->assertSame(301, mb_strlen($result['evidence'][0]));
        $this->assertStringEndsWith('…', $result['evidence'][0]);
    }
}
