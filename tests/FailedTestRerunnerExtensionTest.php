<?php

declare(strict_types=1);

namespace PhpUnitRunFailed\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use PhpUnitRunFailed\FailedTestRerunnerExtension;
use PhpUnitRunFailed\FailureStorage;
use ReflectionClass;

final class FailedTestRerunnerExtensionTest extends TestCase
{
    private FailedTestRerunnerExtension $extension;
    private string $tempFile;
    private array $originalArgv;
    private array $originalServerArgv;

    protected function setUp(): void
    {
        $this->extension = new FailedTestRerunnerExtension();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'phpunit_failed_tests_');

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

    public function testBootstrapWithoutFailedFlag(): void
    {
        $GLOBALS['argv'] = ['phpunit'];
        $_SERVER['argv'] = $GLOBALS['argv'];

        $configuration = $this->createConfigurationInstance();
        $facade = new Facade();
        $parameters = ParameterCollection::fromArray([]);

        try {
            $this->extension->bootstrap($configuration, $facade, $parameters);
        } catch (\PHPUnit\Event\EventFacadeIsSealedException $e) {
        }
        $this->assertEquals(['phpunit'], $GLOBALS['argv']);
    }

    public function testBootstrapWithFailedTestsuiteButNoFailedTests(): void
    {
        $GLOBALS['argv'] = ['phpunit', '--testsuite=failed'];
        $_SERVER['argv'] = $GLOBALS['argv'];

        $configuration = $this->createConfigurationInstance();
        $facade = new Facade();
        $parameters = ParameterCollection::fromArray([]);

        try {
            $this->extension->bootstrap($configuration, $facade, $parameters);
        } catch (\PHPUnit\Event\EventFacadeIsSealedException $e) {
        }

        // Verify configuration was injected (even for empty test suite)
        $this->assertGreaterThan(2, count($GLOBALS['argv']));
        $this->assertStringContainsString('--configuration=', implode(' ', $GLOBALS['argv']));
        $this->assertStringContainsString('--testsuite=failed', implode(' ', $GLOBALS['argv']));
    }

    public function testBootstrapWithFailedTestsuiteAndFailedTests(): void
    {
        // Create failed tests file
        $storage = new FailureStorage($this->tempFile);
        $failedTests = ['TestClass::testMethod' => ['class' => 'TestClass', 'method' => 'testMethod']];
        $storage->saveFailedTests($failedTests);

        // Mock the default storage file path
        $defaultPath = getcwd() . '/.phpunit-failed-tests.json';
        copy($this->tempFile, $defaultPath);

        $GLOBALS['argv'] = ['phpunit', '--testsuite=failed'];
        $_SERVER['argv'] = $GLOBALS['argv'];

        $configuration = $this->createConfigurationInstance();
        $facade = new Facade();
        $parameters = ParameterCollection::fromArray([]);

        try {
            $this->extension->bootstrap($configuration, $facade, $parameters);
        } catch (\PHPUnit\Event\EventFacadeIsSealedException $e) {
        }

        // Verify configuration was injected (argv should now have --configuration argument)
        $this->assertGreaterThan(2, count($GLOBALS['argv']));
        $this->assertStringContainsString('--configuration=', implode(' ', $GLOBALS['argv']));

        // Clean up
        if (file_exists($defaultPath)) {
            unlink($defaultPath);
        }
    }

    public function testIsRunningFailedTestSuiteWithTestsuiteFlag(): void
    {
        $GLOBALS['argv'] = ['phpunit', '--testsuite=failed'];
        $_SERVER['argv'] = $GLOBALS['argv'];

        $reflection = new ReflectionClass($this->extension);
        $method = $reflection->getMethod('isRunningFailedTestSuite');
        $method->setAccessible(true);

        $result = $method->invoke($this->extension);

        $this->assertTrue($result);
    }

    public function testIsRunningFailedTestSuiteWithSeparateFlag(): void
    {
        $GLOBALS['argv'] = ['phpunit', '--testsuite', 'failed'];
        $_SERVER['argv'] = $GLOBALS['argv'];

        $reflection = new ReflectionClass($this->extension);
        $method = $reflection->getMethod('isRunningFailedTestSuite');
        $method->setAccessible(true);

        $result = $method->invoke($this->extension);

        $this->assertTrue($result);
    }

    public function testIsRunningFailedTestSuiteReturnsFalseByDefault(): void
    {
        $GLOBALS['argv'] = ['phpunit'];
        $_SERVER['argv'] = $GLOBALS['argv'];

        $reflection = new ReflectionClass($this->extension);
        $method = $reflection->getMethod('isRunningFailedTestSuite');
        $method->setAccessible(true);

        $result = $method->invoke($this->extension);

        $this->assertFalse($result);
    }
    private function createConfigurationInstance(): Configuration
    {
        // Configuration is final and complex; we only need a typed instance.
        // Use Reflection to instantiate without invoking the constructor.
        $ref = new ReflectionClass(Configuration::class);
        return $ref->newInstanceWithoutConstructor();
    }
}
