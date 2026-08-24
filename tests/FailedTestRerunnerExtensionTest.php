<?php

declare(strict_types=1);

namespace PhpUnitRunFailed\Tests;

use PHPUnit\Event\EventFacadeIsSealedException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use PhpUnitRunFailed\FailedTestRerunnerExtension;
use PhpUnitRunFailed\TestResultCollector;
use ReflectionClass;

final class FailedTestRerunnerExtensionTest extends TestCase
{
    /** @var list<string> */
    private array $originalArgv;

    /** @var list<string> */
    private array $originalServerArgv;

    protected function setUp(): void
    {
        $this->originalArgv = $GLOBALS['argv'] ?? [];
        $this->originalServerArgv = $_SERVER['argv'] ?? [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['argv'] = $this->originalArgv;
        $_SERVER['argv'] = $this->originalServerArgv;
    }

    public function testBootstrapOnARegularRunKeepsTheArgumentsUntouched(): void
    {
        $GLOBALS['argv'] = ['phpunit'];
        $_SERVER['argv'] = $GLOBALS['argv'];

        $this->bootstrapExtension();

        $this->assertSame(['phpunit'], $GLOBALS['argv']);
    }

    public function testBootstrapOnARegularRunStartsFromAnEmptyFailureList(): void
    {
        $GLOBALS['argv'] = ['phpunit'];
        $_SERVER['argv'] = $GLOBALS['argv'];
        TestResultCollector::addFailedTest('LeftOver::testMethod', ['class' => 'LeftOver', 'method' => 'testMethod']);

        $this->bootstrapExtension();

        $this->assertSame([], TestResultCollector::getFailedTests());
    }

    private function bootstrapExtension(): void
    {
        $extension = new FailedTestRerunnerExtension();

        try {
            $extension->bootstrap($this->createConfigurationInstance(), new Facade(), ParameterCollection::fromArray([]));
        } catch (EventFacadeIsSealedException $e) {
            // The event facade is already sealed while the suite is running; the
            // subscribers cannot be registered a second time from within a test.
        }
    }

    private function createConfigurationInstance(): Configuration
    {
        // Configuration is final and complex; we only need a typed instance.
        // Use Reflection to instantiate without invoking the constructor.
        $ref = new ReflectionClass(Configuration::class);

        return $ref->newInstanceWithoutConstructor();
    }
}
