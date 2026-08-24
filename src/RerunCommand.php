<?php

declare(strict_types=1);

namespace PhpUnitRunFailed;

/**
 * Argv handling for the `--testsuite=failed` invocation the extension pries on:
 * detecting it, and rewriting it into the filtered command to re-execute.
 */
final class RerunCommand
{
    /**
     * @param list<string>|null $argv defaults to the current process arguments
     */
    public static function isFailedTestsuiteRun(?array $argv = null): bool
    {
        $argv ??= self::currentArgv();

        foreach ($argv as $i => $arg) {
            if ($arg === '--testsuite' && ($argv[$i + 1] ?? null) === 'failed') {
                return true;
            }

            if ($arg === '--testsuite=failed') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array{class: string, method: string, file?: string, line?: int, failure?: string, error?: string}> $failedTests
     * @param list<string>|null $argv defaults to the current process arguments
     */
    public static function build(array $failedTests, ?array $argv = null): string
    {
        $argv ??= self::currentArgv();

        $args = [];
        $skipNext = false;

        foreach ($argv as $i => $arg) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            if ($arg === '--testsuite' && ($argv[$i + 1] ?? null) === 'failed') {
                $skipNext = true;
                continue;
            }

            if ($arg === '--testsuite=failed') {
                continue;
            }

            $args[] = $arg;
        }

        $args[] = '--filter=' . self::filterPattern($failedTests);

        return implode(' ', array_map('escapeshellarg', $args));
    }

    /**
     * @param array<string, array{class: string, method: string, file?: string, line?: int, failure?: string, error?: string}> $failedTests
     */
    private static function filterPattern(array $failedTests): string
    {
        $patterns = [];
        foreach ($failedTests as $testInfo) {
            $patterns[] = preg_quote($testInfo['class'] . '::' . $testInfo['method'], '/') . '\\b';
        }

        return '(' . implode('|', $patterns) . ')';
    }

    /**
     * @return list<string>
     */
    private static function currentArgv(): array
    {
        /** @var list<string> */
        return $GLOBALS['argv'] ?? $_SERVER['argv'] ?? [];
    }
}
