<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Analysis;

use LlmDispatch\Runner\Analysis\ContaminationDetector;
use PHPUnit\Framework\TestCase;

final class ContaminationDetectorTest extends TestCase
{
    public function testFlagsAbsoluteRepoPathAccess(): void
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

    public function testFlagsFindRootEscape(): void
    {
        $markers = [];
        $detector = new ContaminationDetector($markers);

        $transcript = 'find / -name \'*.json\' 2>/dev/null';
        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertContains('escape:find-root', $result['matches']);
        $this->assertContains($transcript, $result['evidence']);
    }

    public function testFlagsFindHostDirEscape(): void
    {
        $markers = [];
        $detector = new ContaminationDetector($markers);

        $transcript = 'find /opt -name ground-truth';
        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertContains('escape:find-hostdir', $result['matches']);
        $this->assertContains($transcript, $result['evidence']);
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

    public function testFlagsFindUserDirEscape(): void
    {
        $markers = [];
        $detector = new ContaminationDetector($markers);

        $transcript = 'find /Users -name "*.json"';
        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertContains('escape:find-hostdir', $result['matches']);
    }

    public function testFlagsRecursiveGrepFromRoot(): void
    {
        $markers = [];
        $detector = new ContaminationDetector($markers);

        $transcript = 'grep -r "ground-truth" /';
        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertContains('escape:grep-host', $result['matches']);
    }

    public function testFlagsRecursiveGrepFromHostDir(): void
    {
        $markers = [];
        $detector = new ContaminationDetector($markers);

        $transcript = 'grep -rn "answer" /opt';
        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertContains('escape:grep-host', $result['matches']);
    }

    public function testMultipleEscapePatterns(): void
    {
        $markers = [];
        $detector = new ContaminationDetector($markers);

        $transcript = <<<'EOT'
find / -name "*.json"
grep -r "secret" /home
EOT;
        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertCount(2, $result['matches']);
        $this->assertContains('escape:find-root', $result['matches']);
        $this->assertContains('escape:grep-host', $result['matches']);
    }

    public function testMixedMarkerAndEscapePatterns(): void
    {
        $markers = ['forbidden-path'];
        $detector = new ContaminationDetector($markers);

        $transcript = <<<'EOT'
cat forbidden-path/secret.json
find /opt -name test
EOT;
        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertCount(2, $result['matches']);
        $this->assertContains('forbidden-path', $result['matches']);
        $this->assertContains('escape:find-hostdir', $result['matches']);
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
        // This is the critical regression guard against false positives.
        $transcript = 'find /private/tmp/llm-disp-42/mock-project -name \'*.php\'';
        $result = $detector->scan($transcript);

        $this->assertFalse($result['contaminated']);
        $this->assertEmpty($result['matches']);
    }

    public function testFlagsFindWithFlagsBeforePath(): void
    {
        $markers = [];
        $detector = new ContaminationDetector($markers);

        // find with flags like -H before the host directory path should be caught
        $transcript = 'find -H /opt -name ground-truth';
        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertContains('escape:find-hostdir', $result['matches']);
    }

    public function testFlagsCapitalRGrep(): void
    {
        $markers = [];
        $detector = new ContaminationDetector($markers);

        // grep with capital -R (recursive) should be caught, not just lowercase -r
        $transcript = 'grep -R "sqli" /opt';
        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertContains('escape:grep-host', $result['matches']);
    }

    public function testGrepWithinWorkspaceIsClean(): void
    {
        $markers = [];
        $detector = new ContaminationDetector($markers);

        // grep with recursive flag but targeting local directory (no host-dir path) is clean
        $transcript = 'grep -rn "query(" src';
        $result = $detector->scan($transcript);

        $this->assertFalse($result['contaminated']);
        $this->assertEmpty($result['matches']);
    }

    public function testEvidenceCapturesMatchingLines(): void
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
