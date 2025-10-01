<?php

declare(strict_types=1);

namespace PhpUnitRunFailed\Tests;

use Exception;
use PHPUnit\Event\Code\TestDox;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Code\Throwable as EventThrowable;
use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\GarbageCollectorStatus;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\TestData\TestDataCollection;
use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Framework\TestCase;
use PHPUnit\Metadata\MetadataCollection;
use PhpUnitRunFailed\FailedTestTracker;
use PhpUnitRunFailed\FailureStorage;
use ReflectionClass;

final class FailedTestTrackerTest extends TestCase
{
    private FailedTestTracker $tracker;
    private string $tempFile;
    private array $originalArgv;
    private array $originalServerArgv;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'phpunit_failed_tests_');

        $this->tracker = new FailedTestTracker();

        $reflection = new ReflectionClass($this->tracker);
        $prop = $reflection->getProperty('storage');
        $prop->setAccessible(true);
        $prop->setValue($this->tracker, new FailureStorage($this->tempFile));

        // Backup original values
        $this->originalArgv = $GLOBALS['argv'] ?? [];
        $this->originalServerArgv = $_SERVER['argv'] ?? [];
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        // Restore original values
        $GLOBALS['argv'] = $this->originalArgv;
        $_SERVER['argv'] = $this->originalServerArgv;

        // Clean up environment
        putenv('FAILED');
    }

    public function testNotifyFailedTest(): void
    {
        $test = $this->createTestMethod('TestClass', 'testMethod', '/path/to/test.php', 10);
        $throwable = new EventThrowable(Exception::class, 'Test failed', 'Test failed', '', null);
        $event = new Failed($this->createTelemetryInfo(), $test, $throwable, null);
        $this->tracker->notify($event);

        // Verify the failed test was recorded
        $reflection = new ReflectionClass($this->tracker);
        $property = $reflection->getProperty('failedTests');
        $property->setAccessible(true);
        $failedTests = $property->getValue($this->tracker);

        $this->assertArrayHasKey('TestClass::testMethod', $failedTests);
        $this->assertEquals('TestClass', $failedTests['TestClass::testMethod']['class']);
        $this->assertEquals('testMethod', $failedTests['TestClass::testMethod']['method']);
        $this->assertEquals('Test failed', $failedTests['TestClass::testMethod']['failure']);
    }

    public function testNotifyErroredTest(): void
    {
        $test = $this->createTestMethod('TestClass', 'testMethod', '/path/to/test.php', 10);
        $throwable = new EventThrowable(Exception::class, 'Test error', 'Test error', '', null);
        $event = new Errored($this->createTelemetryInfo(), $test, $throwable);
        $this->tracker->notify($event);

        // Verify the errored test was recorded
        $reflection = new ReflectionClass($this->tracker);
        $property = $reflection->getProperty('failedTests');
        $property->setAccessible(true);
        $failedTests = $property->getValue($this->tracker);

        $this->assertArrayHasKey('TestClass::testMethod', $failedTests);
        $this->assertEquals('TestClass', $failedTests['TestClass::testMethod']['class']);
        $this->assertEquals('testMethod', $failedTests['TestClass::testMethod']['method']);
        $this->assertEquals('Test error', $failedTests['TestClass::testMethod']['error']);
    }

    public function testNotifyExecutionFinishedWithNoFailures(): void
    {
        $GLOBALS['argv'] = ['phpunit'];
        $_SERVER['argv'] = $GLOBALS['argv'];

        $event = new ExecutionFinished($this->createTelemetryInfo());

        $this->tracker->notify($event);
        $this->assertFileDoesNotExist($this->tempFile);
    }

    public function testNotifyExecutionFinishedWithFailuresNormalRun(): void
    {
        $test = $this->createTestMethod('TestClass', 'testMethod', '/path/to/test.php', 10);
        $throwable = new EventThrowable(Exception::class, 'Test failed', 'Test failed', '', null);
        $failedEvent = new Failed($this->createTelemetryInfo(), $test, $throwable, null);
        $this->tracker->notify($failedEvent);

        $GLOBALS['argv'] = ['phpunit'];
        $_SERVER['argv'] = $GLOBALS['argv'];

        $event = new ExecutionFinished($this->createTelemetryInfo());
        $this->tracker->notify($event);
        $this->assertFileExists($this->tempFile);
        $contents = json_decode(file_get_contents($this->tempFile), true);
        $this->assertSame(1, $contents['count']);
    }

    public function testNotifyExecutionFinishedWithFailuresFailedRun(): void
    {
        $test = $this->createTestMethod('TestClass', 'testMethod', '/path/to/test.php', 10);
        $throwable = new EventThrowable(Exception::class, 'Test failed', 'Test failed', '', null);
        $failedEvent = new Failed($this->createTelemetryInfo(), $test, $throwable, null);
        $this->tracker->notify($failedEvent);

        $GLOBALS['argv'] = ['phpunit', '--testsuite=failed'];
        $_SERVER['argv'] = $GLOBALS['argv'];

        $event = new ExecutionFinished($this->createTelemetryInfo());
        $this->tracker->notify($event);
        $this->assertFileExists($this->tempFile);
    }

    public function testNotifyExecutionFinishedWithFailuresFailedRunAllPass(): void
    {
        $GLOBALS['argv'] = ['phpunit', '--testsuite=failed'];
        $_SERVER['argv'] = $GLOBALS['argv'];

        $event = new ExecutionFinished($this->createTelemetryInfo());
        $this->tracker->notify($event);
        $this->assertFileDoesNotExist($this->tempFile);
    }

    public function testGetTestIdentifier(): void
    {
        $test = $this->createTestMethod('TestClass', 'testMethod', '/path/to/test.php', 10);

        $reflection = new ReflectionClass($this->tracker);
        $method = $reflection->getMethod('getTestIdentifier');
        $method->setAccessible(true);

        $identifier = $method->invoke($this->tracker, $test);

        $this->assertEquals('TestClass::testMethod', $identifier);
    }

    private function createTestMethod(string $className, string $methodName, string $file, int $line): TestMethod
    {
        return new TestMethod(
            $className,
            $methodName,
            $file,
            $line,
            new TestDox($className, $methodName, $methodName),
            MetadataCollection::fromArray([]),
            TestDataCollection::fromArray([]),
        );
    }

    private function createTelemetryInfo(): Info
    {
        $hrTime = HRTime::fromSecondsAndNanoseconds(0, 0);
        $snapshot = new Snapshot(
            $hrTime,
            MemoryUsage::fromBytes(0),
            MemoryUsage::fromBytes(0),
            new GarbageCollectorStatus(0, 0, 0, 0, null, null, null, null, null, null, null, null),
        );
        return new Info(
            $snapshot,
            Duration::fromSecondsAndNanoseconds(0, 0),
            MemoryUsage::fromBytes(0),
            Duration::fromSecondsAndNanoseconds(0, 0),
            MemoryUsage::fromBytes(0),
        );
    }
}
