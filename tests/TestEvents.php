<?php

declare(strict_types=1);

namespace PhpUnitRunFailed\Tests;

use PHPUnit\Event\Code\TestDox;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\GarbageCollectorStatus;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\TestData\TestDataCollection;
use PHPUnit\Metadata\MetadataCollection;

/**
 * Builders for the PHPUnit event values the subscribers consume.
 */
trait TestEvents
{
    private function createTestMethod(string $className, string $methodName, string $file = '/path/to/test.php', int $line = 10): TestMethod
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
        // GarbageCollectorStatus takes non-nullable scalars since PHPUnit 12;
        // zeroed values keep this working across 10, 11 and 12.
        $snapshot = new Snapshot(
            HRTime::fromSecondsAndNanoseconds(0, 0),
            MemoryUsage::fromBytes(0),
            MemoryUsage::fromBytes(0),
            new GarbageCollectorStatus(0, 0, 0, 0, 0.0, 0.0, 0.0, 0.0, false, false, false, 0),
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
