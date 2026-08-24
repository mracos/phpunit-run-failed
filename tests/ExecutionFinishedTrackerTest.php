<?php

declare(strict_types=1);

namespace PhpUnitRunFailed\Tests;

use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Framework\TestCase;
use PhpUnitRunFailed\ExecutionFinishedTracker;
use PhpUnitRunFailed\FailureStorage;
use PhpUnitRunFailed\TestResultCollector;

final class ExecutionFinishedTrackerTest extends TestCase
{
    use TestEvents;

    private string $storageFile;

    /** @var resource */
    private $output;

    /** @var list<string> */
    private array $originalArgv;

    /** @var list<string> */
    private array $originalServerArgv;

    protected function setUp(): void
    {
        $this->storageFile = (string) tempnam(sys_get_temp_dir(), 'phpunit_failed_tests_');
        unlink($this->storageFile);

        $this->output = fopen('php://memory', 'r+');

        TestResultCollector::clearFailedTests();

        $this->originalArgv = $GLOBALS['argv'] ?? [];
        $this->originalServerArgv = $_SERVER['argv'] ?? [];
    }

    protected function tearDown(): void
    {
        if (file_exists($this->storageFile)) {
            unlink($this->storageFile);
        }

        fclose($this->output);

        TestResultCollector::clearFailedTests();

        $GLOBALS['argv'] = $this->originalArgv;
        $_SERVER['argv'] = $this->originalServerArgv;
    }

    public function testStoresTheFailedTestsOfARegularRun(): void
    {
        $this->runningAs(['phpunit']);
        TestResultCollector::addFailedTest('TestClass::testMethod', ['class' => 'TestClass', 'method' => 'testMethod']);

        $this->notifyExecutionFinished();

        $this->assertSame(
            ['TestClass::testMethod' => ['class' => 'TestClass', 'method' => 'testMethod']],
            (new FailureStorage($this->storageFile))->getFailedTests(),
        );
        $this->assertStringContainsString('1 test(s) failed', $this->reportedMessages());
        $this->assertStringContainsString('--testsuite=failed', $this->reportedMessages());
    }

    public function testClearsTheStoredFailuresWhenNothingFailed(): void
    {
        $this->runningAs(['phpunit']);
        (new FailureStorage($this->storageFile))->saveFailedTests(
            ['TestClass::testMethod' => ['class' => 'TestClass', 'method' => 'testMethod']],
        );

        $this->notifyExecutionFinished();

        $this->assertFileDoesNotExist($this->storageFile);
    }

    public function testKeepsTheFailuresStillFailingAfterARerun(): void
    {
        $this->runningAs(['phpunit', '--testsuite=failed']);
        TestResultCollector::addFailedTest('TestClass::testMethod', ['class' => 'TestClass', 'method' => 'testMethod']);

        $this->notifyExecutionFinished();

        $this->assertTrue((new FailureStorage($this->storageFile))->hasFailedTests());
        $this->assertStringContainsString('1 test(s) still failing after re-run', $this->reportedMessages());
    }

    public function testClearsTheFailuresWhenTheRerunPasses(): void
    {
        $this->runningAs(['phpunit', '--testsuite=failed']);
        (new FailureStorage($this->storageFile))->saveFailedTests(
            ['TestClass::testMethod' => ['class' => 'TestClass', 'method' => 'testMethod']],
        );

        $this->notifyExecutionFinished();

        $this->assertFileDoesNotExist($this->storageFile);
        $this->assertStringContainsString('All previously failed tests now pass!', $this->reportedMessages());
    }

    /**
     * @param list<string> $argv
     */
    private function runningAs(array $argv): void
    {
        $GLOBALS['argv'] = $argv;
        $_SERVER['argv'] = $argv;
    }

    private function notifyExecutionFinished(): void
    {
        $tracker = new ExecutionFinishedTracker(new FailureStorage($this->storageFile), $this->output);

        $tracker->notify(new ExecutionFinished($this->createTelemetryInfo()));
    }

    private function reportedMessages(): string
    {
        rewind($this->output);

        return (string) stream_get_contents($this->output);
    }
}
