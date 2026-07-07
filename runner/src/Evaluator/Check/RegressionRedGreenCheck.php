<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\CheckInterface;
use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Support\ProcessExecutor;

final class RegressionRedGreenCheck implements CheckInterface
{
    private const BASELINE_REF = 'baseline';
    private const TEST_FILE_PATTERN = '#^mock-project/tests/.+Test\.php$#';

    private readonly ProcessExecutor $executor;

    public function __construct(?ProcessExecutor $executor = null)
    {
        $this->executor = $executor ?? new ProcessExecutor();
    }

    public function run(string $worktreePath): CheckResult
    {
        $diffRes = $this->executor->exec($worktreePath, [
            'git', 'diff', '--name-only', '--diff-filter=AM', self::BASELINE_REF,
        ]);

        if ($diffRes->exitCode !== 0) {
            return new CheckResult(
                type: 'regression_red_green',
                passed: false,
                details: ['error' => trim($diffRes->stderr)],
            );
        }

        $testFiles = [];
        foreach (explode("\n", trim($diffRes->stdout)) as $line) {
            if ($line !== '' && preg_match(self::TEST_FILE_PATTERN, $line) === 1) {
                $testFiles[] = $line;
            }
        }

        if ($testFiles === []) {
            return new CheckResult(
                type: 'regression_red_green',
                passed: false,
                details: ['reason' => 'no_regression_test'],
            );
        }

        $rrgDir = $worktreePath . '/.rrg';

        try {
            $baselineCopyResult = $this->prepareBaselineCopy($worktreePath, $rrgDir, $testFiles);
            if ($baselineCopyResult !== null) {
                return $baselineCopyResult;
            }

            $relativeTestPaths = array_map(
                static fn (string $file): string => substr($file, strlen('mock-project/')),
                $testFiles,
            );
            $command = ['./vendor/bin/phpunit', ...$relativeTestPaths];

            $redRes = $this->executor->exec($rrgDir . '/mock-project', $command);
            $greenRes = $this->executor->exec($worktreePath . '/mock-project', $command);

            $red = $redRes->exitCode !== 0;
            $green = $greenRes->exitCode === 0;

            return new CheckResult(
                type: 'regression_red_green',
                passed: $red && $green,
                details: [
                    'test_files' => $testFiles,
                    'red_exit' => $redRes->exitCode,
                    'green_exit' => $greenRes->exitCode,
                ],
            );
        } finally {
            $this->removeRecursive($rrgDir);
        }
    }

    /**
     * @param list<string> $testFiles
     * @return ?CheckResult null on success, CheckResult on failure
     */
    private function prepareBaselineCopy(string $worktreePath, string $rrgDir, array $testFiles): ?CheckResult
    {
        $archiveRes = $this->executor->exec($worktreePath, [
            'git', 'archive', '--format=tar', '--output=.rrg.tar', self::BASELINE_REF,
        ]);

        if ($archiveRes->exitCode !== 0) {
            return new CheckResult(
                type: 'regression_red_green',
                passed: false,
                details: [
                    'reason' => 'baseline_copy_failed',
                    'error' => trim($archiveRes->stderr),
                ],
            );
        }

        if (!is_dir($rrgDir)) {
            mkdir($rrgDir, 0777, true);
        }

        $tarRes = $this->executor->exec($rrgDir, ['tar', '-xf', '../.rrg.tar']);

        if ($tarRes->exitCode !== 0) {
            $tarPath = $worktreePath . '/.rrg.tar';
            if (is_file($tarPath)) {
                unlink($tarPath);
            }
            return new CheckResult(
                type: 'regression_red_green',
                passed: false,
                details: [
                    'reason' => 'baseline_copy_failed',
                    'error' => trim($tarRes->stderr),
                ],
            );
        }

        $tarPath = $worktreePath . '/.rrg.tar';
        if (file_exists($tarPath)) {
            unlink($tarPath);
        }

        foreach ($testFiles as $file) {
            $source = $worktreePath . '/' . $file;
            $dest = $rrgDir . '/' . $file;
            $destDir = dirname($dest);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }
            copy($source, $dest);
        }

        $vendorSource = $worktreePath . '/mock-project/vendor';
        $vendorDest = $rrgDir . '/mock-project/vendor';
        if (is_dir($vendorSource) && !is_link($vendorDest) && !file_exists($vendorDest)) {
            mkdir($vendorDest, 0777, true);
            // Composer's autoload glue (composer/, autoload.php) and bin proxies
            // compute paths from __DIR__, which resolves through symlinks to the
            // agent's real tree; they must physically live under the baseline
            // copy. Package dirs are safe to symlink.
            foreach (scandir($vendorSource) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $source = $vendorSource . '/' . $entry;
                $dest = $vendorDest . '/' . $entry;
                if ($entry === 'composer' || $entry === 'bin') {
                    $this->copyRecursive($source, $dest);
                } elseif ($entry === 'autoload.php') {
                    copy($source, $dest);
                } else {
                    symlink($source, $dest);
                }
            }
        }

        return null;
    }

    private function copyRecursive(string $source, string $dest): void
    {
        if (is_dir($source)) {
            if (!is_dir($dest)) {
                mkdir($dest, 0777, true);
            }
            foreach (scandir($source) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $this->copyRecursive($source . '/' . $entry, $dest . '/' . $entry);
            }
            return;
        }
        copy($source, $dest);
    }

    private function removeRecursive(string $target): void
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
                $this->removeRecursive($target . '/' . $child);
            }
            @rmdir($target);
        }
    }
}
