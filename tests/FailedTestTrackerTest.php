<?php

declare(strict_types=1);

namespace PhpUnitRunFailed\Tests;

use Exception;
use PHPUnit\Event\Code\Throwable as EventThrowable;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Framework\TestCase;
use PhpUnitRunFailed\ErroredTestTracker;
use PhpUnitRunFailed\FailedTestTracker;
use PhpUnitRunFailed\TestResultCollector;

final class FailedTestTrackerTest extends TestCase
{
    use TestEvents;

    protected function setUp(): void
    {
        TestResultCollector::clearFailedTests();
    }

    protected function tearDown(): void
    {
        TestResultCollector::clearFailedTests();
    }

    public function testCollectsAFailedTest(): void
    {
        $event = new Failed(
            $this->createTelemetryInfo(),
            $this->createTestMethod('TestClass', 'testMethod'),
            new EventThrowable(Exception::class, 'Test failed', 'Test failed', '', null),
            null,
        );

        (new FailedTestTracker())->notify($event);

        $collected = TestResultCollector::getFailedTests();
        $this->assertArrayHasKey('TestClass::testMethod', $collected);
        $this->assertSame('TestClass', $collected['TestClass::testMethod']['class']);
        $this->assertSame('testMethod', $collected['TestClass::testMethod']['method']);
        $this->assertSame('Test failed', $collected['TestClass::testMethod']['failure']);
    }

    public function testCollectsAnErroredTest(): void
    {
        $event = new Errored(
            $this->createTelemetryInfo(),
            $this->createTestMethod('TestClass', 'testMethod'),
            new EventThrowable(Exception::class, 'Test error', 'Test error', '', null),
        );

        (new ErroredTestTracker())->notify($event);

        $collected = TestResultCollector::getFailedTests();
        $this->assertArrayHasKey('TestClass::testMethod', $collected);
        $this->assertSame('Test error', $collected['TestClass::testMethod']['error']);
    }

    public function testCollectsTheFileAndLineOfTheFailure(): void
    {
        $event = new Failed(
            $this->createTelemetryInfo(),
            $this->createTestMethod('TestClass', 'testMethod', '/path/to/TestClass.php', 42),
            new EventThrowable(Exception::class, 'Test failed', 'Test failed', '', null),
            null,
        );

        (new FailedTestTracker())->notify($event);

        $collected = TestResultCollector::getFailedTests();
        $this->assertSame('/path/to/TestClass.php', $collected['TestClass::testMethod']['file']);
        $this->assertSame(42, $collected['TestClass::testMethod']['line']);
    }
}
