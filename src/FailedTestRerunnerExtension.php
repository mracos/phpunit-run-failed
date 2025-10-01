<?php

declare(strict_types=1);

namespace PhpUnitRunFailed;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

final class FailedTestRerunnerExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        if ($this->isRunningFailedTestsuite()) {
            $this->handleFailedTestsuite();
            return;
        }

        TestResultCollector::clearFailedTests();

        $facade->registerSubscriber(new FailedTestTracker());
        $facade->registerSubscriber(new ErroredTestTracker());
        $facade->registerSubscriber(new ExecutionFinishedTracker());
    }

    private function isRunningFailedTestsuite(): bool
    {
        $args = $GLOBALS['argv'] ?? $_SERVER['argv'] ?? [];

        foreach ($args as $i => $arg) {
            if ($arg === '--testsuite' && isset($args[$i + 1]) && $args[$i + 1] === 'failed') {
                return true;
            }
            if ($arg === '--testsuite=failed') {
                return true;
            }
        }

        return false;
    }

    private function handleFailedTestsuite(): void
    {
        if (empty($failedTests)) {
            fwrite(STDERR, "No failed tests found to re-run.\n");
            exit(0);
        }

        fwrite(STDERR, 'Running ' . count($failedTests) . " failed tests...\n");

        $filterPattern = $this->generateFilterPattern();
        $this->reexecWithFilter($filterPattern);
    }

    private function generateFilterPattern(): string
    {
        $storage = new FailureStorage();
        $failedTests = $storage->getFailedTests();

        $patterns = [];
        foreach ($failedTests as $testId => $testInfo) {
            $className = $testInfo['class'];
            $methodName = $testInfo['method'];
            $patterns[] = preg_quote($className . '::' . $methodName, '/') . '\\b';
        }

        return '(' . implode('|', $patterns) . ')';
    }

    private function reexecWithFilter(string $filterPattern): void
    {
        if (isset($GLOBALS['argv'])) {
            $args = $GLOBALS['argv'];
            $newArgs = [];
            $skipNext = false;

            foreach ($args as $i => $arg) {
                if ($skipNext) {
                    $skipNext = false;
                    continue;
                }

                if ($arg === '--testsuite' && isset($args[$i + 1]) && $args[$i + 1] === 'failed') {
                    $skipNext = true;
                    continue;
                }
                if ($arg === '--testsuite=failed') {
                    continue;
                }

                $newArgs[] = $arg;
            }

            $newArgs[] = '--filter=' . $filterPattern;

            $command = implode(' ', array_map('escapeshellarg', $newArgs));
            fwrite(STDERR, 'Executing: ' . $command . "\n");

            passthru($command, $exitCode);
            exit($exitCode);
        }
    }

}
