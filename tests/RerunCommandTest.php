<?php

declare(strict_types=1);

namespace PhpUnitRunFailed\Tests;

use PHPUnit\Framework\TestCase;
use PhpUnitRunFailed\RerunCommand;

final class RerunCommandTest extends TestCase
{
    public function testDetectsTheFailedTestsuiteAsASingleArgument(): void
    {
        $this->assertTrue(RerunCommand::isFailedTestsuiteRun(['phpunit', '--testsuite=failed']));
    }

    public function testDetectsTheFailedTestsuiteAsTwoArguments(): void
    {
        $this->assertTrue(RerunCommand::isFailedTestsuiteRun(['phpunit', '--testsuite', 'failed']));
    }

    public function testDoesNotDetectAnotherTestsuite(): void
    {
        $this->assertFalse(RerunCommand::isFailedTestsuiteRun(['phpunit', '--testsuite', 'unit']));
    }

    public function testDoesNotDetectAPlainRun(): void
    {
        $this->assertFalse(RerunCommand::isFailedTestsuiteRun(['phpunit']));
    }

    public function testBuildFiltersOnTheFailedTests(): void
    {
        $failedTests = [
            'TestClass::testMethod' => ['class' => 'TestClass', 'method' => 'testMethod'],
            'OtherTest::testOther' => ['class' => 'OtherTest', 'method' => 'testOther'],
        ];

        $command = RerunCommand::build($failedTests, ['phpunit', '--testsuite=failed']);

        $this->assertSame("'phpunit' '--filter=(TestClass\\:\\:testMethod\\b|OtherTest\\:\\:testOther\\b)'", $command);
    }

    public function testBuildDropsTheFailedTestsuiteGivenAsTwoArguments(): void
    {
        $failedTests = ['TestClass::testMethod' => ['class' => 'TestClass', 'method' => 'testMethod']];

        $command = RerunCommand::build($failedTests, ['phpunit', '--testsuite', 'failed', '--colors=always']);

        $this->assertSame("'phpunit' '--colors=always' '--filter=(TestClass\\:\\:testMethod\\b)'", $command);
    }

    public function testBuildKeepsTheOtherArguments(): void
    {
        $failedTests = ['TestClass::testMethod' => ['class' => 'TestClass', 'method' => 'testMethod']];

        $command = RerunCommand::build($failedTests, ['phpunit', '--testsuite=failed', '--configuration=phpunit.xml']);

        $this->assertStringContainsString("'--configuration=phpunit.xml'", $command);
        $this->assertStringNotContainsString('--testsuite', $command);
    }

    public function testBuildEscapesNamespacedTestClasses(): void
    {
        $failedTests = ['App\\Tests\\FooTest::testBar' => ['class' => 'App\\Tests\\FooTest', 'method' => 'testBar']];

        $command = RerunCommand::build($failedTests, ['phpunit', '--testsuite=failed']);

        $this->assertStringContainsString('App\\\\Tests\\\\FooTest\\:\\:testBar', $command);
    }
}
