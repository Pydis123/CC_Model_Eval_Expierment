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
    }

    public function testFlagsFindRootEscape(): void
    {
        $markers = [];
        $detector = new ContaminationDetector($markers);

        $transcript = 'find / -name \'*.json\' 2>/dev/null';
        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertContains('escape:find-root', $result['matches']);
    }

    public function testFlagsFindHostDirEscape(): void
    {
        $markers = [];
        $detector = new ContaminationDetector($markers);

        $transcript = 'find /opt -name ground-truth';
        $result = $detector->scan($transcript);

        $this->assertTrue($result['contaminated']);
        $this->assertContains('escape:find-hostdir', $result['matches']);
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
    }

    public function testEmptyTranscriptIsClean(): void
    {
        $markers = ['/opt/homebrew/var/www/cc/llm-dispatch-experiment/tasks'];
        $detector = new ContaminationDetector($markers);

        $result = $detector->scan('');

        $this->assertFalse($result['contaminated']);
        $this->assertEmpty($result['matches']);
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
}
