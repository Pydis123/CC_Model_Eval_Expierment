<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\Check\RegressionRedGreenCheck;
use LlmDispatch\Runner\Support\ProcessExecutor;
use LlmDispatch\Runner\Support\ProcessResult;
use PHPUnit\Framework\TestCase;

final class RegressionRedGreenCheckTest extends TestCase
{
    private ?string $tmpDir = null;

    protected function tearDown(): void
    {
        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            $this->removeDirRecursive($this->tmpDir);
        }
        $this->tmpDir = null;
    }

    public function testFailsWithoutNewTestFile(): void
    {
        $called = [];
        $stub = $this->stubExecutor(function (string $cwd, array $command) use (&$called): ProcessResult {
            $called[] = $command;
            if ($command[0] === 'git' && $command[1] === 'diff') {
                return new ProcessResult(0, '', '');
            }
            $this->fail('Unexpected command executed: ' . implode(' ', $command));
        });

        $check = new RegressionRedGreenCheck($stub);
        $result = $check->run('/tmp/does-not-need-to-exist-for-this-path');

        $this->assertFalse($result->passed);
        $this->assertSame('regression_red_green', $result->type);
        $this->assertSame('no_regression_test', $result->details['reason']);

        foreach ($called as $command) {
            $this->assertNotSame('./vendor/bin/phpunit', $command[0]);
        }
    }

    public function testPassesWhenRedOnBaselineGreenInTree(): void
    {
        $this->tmpDir = $this->makeWorktreeWithTestFile();

        $stub = $this->stubExecutor(function (string $cwd, array $command): ProcessResult {
            if ($command[0] === 'git' && $command[1] === 'diff') {
                return new ProcessResult(0, "mock-project/tests/Unit/FooTest.php\n", '');
            }
            if ($command[0] === 'git' && $command[1] === 'archive') {
                return new ProcessResult(0, '', '');
            }
            if ($command[0] === 'tar') {
                return new ProcessResult(0, '', '');
            }
            if ($command[0] === './vendor/bin/phpunit') {
                return str_ends_with($cwd, '.rrg/mock-project')
                    ? new ProcessResult(1, 'FAILURES', '')
                    : new ProcessResult(0, 'OK', '');
            }
            $this->fail('Unexpected command executed: ' . implode(' ', $command));
        });

        $check = new RegressionRedGreenCheck($stub);
        $result = $check->run($this->tmpDir);

        $this->assertTrue($result->passed);
        $this->assertSame(1, $result->details['red_exit']);
        $this->assertSame(0, $result->details['green_exit']);
        $this->assertSame(['mock-project/tests/Unit/FooTest.php'], $result->details['test_files']);
        $this->assertFalse(is_dir($this->tmpDir . '/.rrg'), '.rrg scratch dir should be removed after the check runs');
    }

    public function testFailsWhenTestAlreadyPassesOnBaseline(): void
    {
        $this->tmpDir = $this->makeWorktreeWithTestFile();

        $stub = $this->stubExecutor(function (string $cwd, array $command): ProcessResult {
            if ($command[0] === 'git' && $command[1] === 'diff') {
                return new ProcessResult(0, "mock-project/tests/Unit/FooTest.php\n", '');
            }
            if ($command[0] === 'git' && $command[1] === 'archive') {
                return new ProcessResult(0, '', '');
            }
            if ($command[0] === 'tar') {
                return new ProcessResult(0, '', '');
            }
            if ($command[0] === './vendor/bin/phpunit') {
                return new ProcessResult(0, 'OK', '');
            }
            $this->fail('Unexpected command executed: ' . implode(' ', $command));
        });

        $check = new RegressionRedGreenCheck($stub);
        $result = $check->run($this->tmpDir);

        $this->assertFalse($result->passed);
        $this->assertSame(0, $result->details['red_exit']);
        $this->assertFalse(is_dir($this->tmpDir . '/.rrg'), '.rrg scratch dir should be removed after the check runs');
    }

    public function testFailsWhenBaselineArchiveFails(): void
    {
        $this->tmpDir = $this->makeWorktreeWithTestFile();

        $stub = $this->stubExecutor(function (string $cwd, array $command): ProcessResult {
            if ($command[0] === 'git' && $command[1] === 'diff') {
                return new ProcessResult(0, "mock-project/tests/Unit/FooTest.php\n", '');
            }
            if ($command[0] === 'git' && $command[1] === 'archive') {
                return new ProcessResult(128, '', 'fatal: not a valid object name: baseline');
            }
            if ($command[0] === './vendor/bin/phpunit') {
                $this->fail('phpunit should not be executed when baseline copy fails');
            }
            $this->fail('Unexpected command executed: ' . implode(' ', $command));
        });

        $check = new RegressionRedGreenCheck($stub);
        $result = $check->run($this->tmpDir);

        $this->assertFalse($result->passed);
        $this->assertSame('regression_red_green', $result->type);
        $this->assertSame('baseline_copy_failed', $result->details['reason']);
        $this->assertStringContainsString('not a valid object name', $result->details['error']);
        $this->assertFalse(is_dir($this->tmpDir . '/.rrg'), '.rrg scratch dir should be removed after the check runs');
    }

    public function testBaselineVendorGlueIsPhysicalNotSymlinked(): void
    {
        $this->tmpDir = $this->makeWorktreeWithVendorStructure();

        $stub = $this->stubExecutor(function (string $cwd, array $command): ProcessResult {
            if ($command[0] === 'git' && $command[1] === 'diff') {
                return new ProcessResult(0, "mock-project/tests/Unit/FooTest.php\n", '');
            }
            if ($command[0] === 'git' && $command[1] === 'archive') {
                return new ProcessResult(0, '', '');
            }
            if ($command[0] === 'tar') {
                return new ProcessResult(0, '', '');
            }
            if ($command[0] === './vendor/bin/phpunit') {
                // Verify baseline vendor layout during phpunit execution (before .rrg is deleted)
                if (str_ends_with($cwd, '.rrg/mock-project')) {
                    $this->assertTrue(is_dir($cwd . '/vendor'));
                    $this->assertFalse(is_link($cwd . '/vendor'));
                    $this->assertTrue(is_file($cwd . '/vendor/autoload.php'));
                    $this->assertFalse(is_link($cwd . '/vendor/autoload.php'));
                    $this->assertTrue(is_dir($cwd . '/vendor/composer'));
                    $this->assertFalse(is_link($cwd . '/vendor/composer'));
                    $this->assertTrue(is_file($cwd . '/vendor/composer/autoload_real.php'));
                    $this->assertFalse(is_link($cwd . '/vendor/composer/autoload_real.php'));
                    $this->assertTrue(is_dir($cwd . '/vendor/bin'));
                    $this->assertFalse(is_link($cwd . '/vendor/bin'));
                    $this->assertTrue(is_file($cwd . '/vendor/bin/phpunit'));
                    $this->assertFalse(is_link($cwd . '/vendor/bin/phpunit'));
                    // Package dirs should still be symlinked to the agent's real tree
                    $this->assertTrue(is_link($cwd . '/vendor/psr'));
                    $this->assertSame(
                        $this->tmpDir . '/mock-project/vendor/psr',
                        readlink($cwd . '/vendor/psr'),
                    );
                }
                return str_ends_with($cwd, '.rrg/mock-project')
                    ? new ProcessResult(1, 'FAILURES', '')
                    : new ProcessResult(0, 'OK', '');
            }
            $this->fail('Unexpected command executed: ' . implode(' ', $command));
        });

        $check = new RegressionRedGreenCheck($stub);
        $result = $check->run($this->tmpDir);

        $this->assertTrue($result->passed);
    }

    private function makeWorktreeWithTestFile(): string
    {
        $dir = sys_get_temp_dir() . '/rrg-check-' . bin2hex(random_bytes(8));
        mkdir($dir . '/mock-project/tests/Unit', 0777, true);
        file_put_contents(
            $dir . '/mock-project/tests/Unit/FooTest.php',
            "<?php\n// placeholder regression test fixture\n",
        );

        return $dir;
    }

    private function makeWorktreeWithVendorStructure(): string
    {
        $dir = sys_get_temp_dir() . '/rrg-check-' . bin2hex(random_bytes(8));
        mkdir($dir . '/mock-project/tests/Unit', 0777, true);
        mkdir($dir . '/mock-project/vendor/composer', 0777, true);
        mkdir($dir . '/mock-project/vendor/bin', 0777, true);
        mkdir($dir . '/mock-project/vendor/psr/log', 0777, true);

        file_put_contents(
            $dir . '/mock-project/tests/Unit/FooTest.php',
            "<?php\n// placeholder regression test fixture\n",
        );
        file_put_contents(
            $dir . '/mock-project/vendor/autoload.php',
            "<?php\n// autoload stub\n",
        );
        file_put_contents(
            $dir . '/mock-project/vendor/composer/autoload_real.php',
            "<?php\n// composer autoload stub\n",
        );
        file_put_contents(
            $dir . '/mock-project/vendor/bin/phpunit',
            "<?php\n// phpunit stub\n",
        );
        file_put_contents(
            $dir . '/mock-project/vendor/psr/log/LoggerInterface.php',
            "<?php\ninterface LoggerInterface {}\n",
        );

        return $dir;
    }

    private function removeDirRecursive(string $target): void
    {
        if (is_link($target) || is_file($target)) {
            @unlink($target);
            return;
        }
        if (is_dir($target)) {
            foreach (scandir($target) ?: [] as $child) {
                if ($child === '.' || $child === '..') {
                    continue;
                }
                $this->removeDirRecursive($target . '/' . $child);
            }
            @rmdir($target);
        }
    }

    private function stubExecutor(\Closure $callback): ProcessExecutor
    {
        return new class($callback) extends ProcessExecutor {
            public function __construct(private readonly \Closure $callback) {}
            public function exec(string $cwd, array $command, ?array $env = null): ProcessResult
            {
                return ($this->callback)($cwd, $command);
            }
        };
    }
}
