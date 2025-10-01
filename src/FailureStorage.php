<?php

declare(strict_types=1);

namespace PhpUnitRunFailed;

use RuntimeException;

final class FailureStorage
{
    private string $storageFile;

    public function __construct(string $storageFile = null)
    {
        $this->storageFile = $storageFile ?? $this->getDefaultStorageFile();
    }

    /**
     * @param array<string, array{class: string, method: string, file?: string, line?: int, failure?: string, error?: string}> $failedTests
     */
    public function saveFailedTests(array $failedTests): void
    {
        if (empty($failedTests)) {
            $this->clearFailedTests();
            return;
        }

        $data = [
            'timestamp' => time(),
            'phpunit_version' => $this->getPHPUnitVersion(),
            'failed_tests' => $failedTests,
            'count' => count($failedTests),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode failed tests to JSON');
        }

        $dir = dirname($this->storageFile);
        if (!is_dir($dir)) {
            $isAbsolute = str_starts_with($dir, DIRECTORY_SEPARATOR);
            $cwd = rtrim((string) getcwd(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if ($isAbsolute && strpos($dir, $cwd) !== 0) {
                throw new RuntimeException('Directory does not exist for storage file: ' . $dir);
            }
            if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException('Failed to create directory for storage file: ' . $dir);
            }
        }

        $tmp = $this->storageFile . '.tmp';
        $bytes = @file_put_contents($tmp, $json, LOCK_EX);
        if ($bytes === false) {
            if (file_exists($tmp)) {
                @unlink($tmp);
            }
            throw new RuntimeException('Failed to write failed tests to temp file: ' . $tmp);
        }

        if (!@rename($tmp, $this->storageFile)) {
            @unlink($tmp);
            throw new RuntimeException('Failed to move temp file to storage file: ' . $this->storageFile);
        }
    }

    /**
     * @return array<string, array{class: string, method: string, file?: string, line?: int, failure?: string, error?: string}>
     */
    public function getFailedTests(): array
    {
        if (!file_exists($this->storageFile)) {
            return [];
        }

        $content = @file_get_contents($this->storageFile);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            // Corrupt JSON; clear file to recover gracefully
            $this->clearFailedTests();
            return [];
        }

        return $data['failed_tests'] ?? [];
    }

    public function clearFailedTests(): void
    {
        if (file_exists($this->storageFile)) {
            @unlink($this->storageFile);
        }
    }

    public function hasFailedTests(): bool
    {
        return file_exists($this->storageFile) && !empty($this->getFailedTests());
    }

    private function getDefaultStorageFile(): string
    {
        return getcwd() . '/.phpunit-failed-tests.json';
    }

    public function getStorageFile(): string
    {
        return $this->storageFile;
    }

    private function getPHPUnitVersion(): string
    {
        if (class_exists('\PHPUnit\Runner\Version')) {
            return \PHPUnit\Runner\Version::id();
        }

        return 'unknown';
    }

    /**
     * @return array{
     *   count: int,
     *   timestamp: int|null,
     *   phpunit_version?: string,
     *   tests: array<string, array{class: string, method: string, file?: string, line?: int, failure?: string, error?: string}>
     * }
     */
    public function getFailedTestsInfo(): array
    {
        if (!file_exists($this->storageFile)) {
            return [
                'count' => 0,
                'timestamp' => null,
                'tests' => [],
            ];
        }

        $content = @file_get_contents($this->storageFile);
        if ($content === false) {
            return [
                'count' => 0,
                'timestamp' => null,
                'tests' => [],
            ];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            $this->clearFailedTests();
            return [
                'count' => 0,
                'timestamp' => null,
                'tests' => [],
            ];
        }

        return [
            'count' => $data['count'] ?? count($data['failed_tests'] ?? []),
            'timestamp' => $data['timestamp'] ?? null,
            'phpunit_version' => $data['phpunit_version'] ?? 'unknown',
            'tests' => $data['failed_tests'] ?? [],
        ];
    }
}
