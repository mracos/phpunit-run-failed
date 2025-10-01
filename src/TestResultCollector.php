<?php

declare(strict_types=1);

namespace PhpUnitRunFailed;

final class TestResultCollector
{
    /**
     * @var array<string, array{class: string, method: string, file?: string, line?: int, failure?: string, error?: string}>
     */
    private static array $failedTests = [];

    /**
     * @param array{class: string, method: string, file?: string, line?: int, failure?: string, error?: string} $testData
     */
    public static function addFailedTest(string $testId, array $testData): void
    {
        self::$failedTests[$testId] = $testData;
    }

    /**
     * @return array<string, array{class: string, method: string, file?: string, line?: int, failure?: string, error?: string}>
     */
    public static function getFailedTests(): array
    {
        return self::$failedTests;
    }

    public static function clearFailedTests(): void
    {
        self::$failedTests = [];
    }
}
