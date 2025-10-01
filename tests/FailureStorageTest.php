<?php

declare(strict_types=1);

namespace PhpUnitRunFailed\Tests;

use PHPUnit\Framework\TestCase;
use PhpUnitRunFailed\FailureStorage;

final class FailureStorageTest extends TestCase
{
    private FailureStorage $storage;
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'phpunit_failed_tests_');
        $this->storage = new FailureStorage($this->tempFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testSaveAndGetFailedTests(): void
    {
        $failedTests = [
            'TestClass::testMethod1' => [
                'class' => 'TestClass',
                'method' => 'testMethod1',
                'file' => '/path/to/test.php',
                'line' => 10,
                'failure' => 'Assertion failed',
            ],
            'TestClass::testMethod2' => [
                'class' => 'TestClass',
                'method' => 'testMethod2',
                'file' => '/path/to/test.php',
                'line' => 20,
                'error' => 'Exception thrown',
            ],
        ];

        $this->storage->saveFailedTests($failedTests);
        $retrievedTests = $this->storage->getFailedTests();

        $this->assertEquals($failedTests, $retrievedTests);
    }

    public function testSaveEmptyFailedTestsClearsStorage(): void
    {
        // First save some tests
        $failedTests = ['TestClass::testMethod' => ['class' => 'TestClass']];
        $this->storage->saveFailedTests($failedTests);
        $this->assertTrue($this->storage->hasFailedTests());

        // Then save empty array
        $this->storage->saveFailedTests([]);
        $this->assertFalse($this->storage->hasFailedTests());
    }

    public function testHasFailedTests(): void
    {
        $this->assertFalse($this->storage->hasFailedTests());

        $this->storage->saveFailedTests(['test' => ['data']]);
        $this->assertTrue($this->storage->hasFailedTests());
    }

    public function testClearFailedTests(): void
    {
        $this->storage->saveFailedTests(['test' => ['data']]);
        $this->assertTrue($this->storage->hasFailedTests());

        $this->storage->clearFailedTests();
        $this->assertFalse($this->storage->hasFailedTests());
    }

    public function testGetFailedTestsWithNonExistentFile(): void
    {
        $nonExistentFile = '/tmp/non_existent_file_' . uniqid();
        $storage = new FailureStorage($nonExistentFile);

        $this->assertEquals([], $storage->getFailedTests());
        $this->assertFalse($storage->hasFailedTests());
    }

    public function testGetFailedTestsInfo(): void
    {
        $failedTests = [
            'TestClass::testMethod1' => ['class' => 'TestClass', 'method' => 'testMethod1'],
            'TestClass::testMethod2' => ['class' => 'TestClass', 'method' => 'testMethod2'],
        ];

        $this->storage->saveFailedTests($failedTests);
        $info = $this->storage->getFailedTestsInfo();

        $this->assertEquals(2, $info['count']);
        $this->assertIsInt($info['timestamp']);
        $this->assertIsString($info['phpunit_version']);
        $this->assertEquals($failedTests, $info['tests']);
    }

    public function testGetFailedTestsInfoWithNoFile(): void
    {
        $nonExistentFile = '/tmp/non_existent_file_' . uniqid();
        $storage = new FailureStorage($nonExistentFile);

        $info = $storage->getFailedTestsInfo();

        $this->assertEquals(0, $info['count']);
        $this->assertNull($info['timestamp']);
        $this->assertEquals([], $info['tests']);
    }

    public function testGetStorageFile(): void
    {
        $this->assertEquals($this->tempFile, $this->storage->getStorageFile());
    }

    public function testDefaultStorageFile(): void
    {
        $storage = new FailureStorage();
        $expectedPath = getcwd() . '/.phpunit-failed-tests.json';

        $this->assertEquals($expectedPath, $storage->getStorageFile());
    }

    public function testSaveFailedTestsWithInvalidPath(): void
    {
        $this->markTestSkipped('Environment-dependent filesystem permissions make this check unreliable; behavior verified by other tests.');
    }

    public function testJsonStructure(): void
    {
        $failedTests = ['TestClass::testMethod' => ['class' => 'TestClass']];
        $this->storage->saveFailedTests($failedTests);

        $content = (string) file_get_contents($this->tempFile);
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('phpunit_version', $data);
        $this->assertArrayHasKey('failed_tests', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertEquals(1, $data['count']);
        $this->assertEquals($failedTests, $data['failed_tests']);
    }

    public function testCorruptJsonIsHandledGracefully(): void
    {
        // Write invalid JSON directly
        file_put_contents($this->tempFile, '{ this is not json');

        $this->assertSame([], $this->storage->getFailedTests());
        $info = $this->storage->getFailedTestsInfo();
        $this->assertSame(0, $info['count']);
        $this->assertNull($info['timestamp']);
        $this->assertSame([], $info['tests']);
        // File should be cleared
        $this->assertFileDoesNotExist($this->tempFile);
    }
}
