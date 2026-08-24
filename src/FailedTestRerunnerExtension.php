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
        if (RerunCommand::isFailedTestsuiteRun()) {
            // The re-run replaces this process, so nothing else may happen here.
            exit($this->rerunFailedTests(new FailureStorage()));
        }

        TestResultCollector::clearFailedTests();

        $facade->registerSubscriber(new FailedTestTracker());
        $facade->registerSubscriber(new ErroredTestTracker());
        $facade->registerSubscriber(new ExecutionFinishedTracker());
    }

    /**
     * @return int the exit code the current process should terminate with
     */
    private function rerunFailedTests(FailureStorage $storage): int
    {
        $failedTests = $storage->getFailedTests();

        if (empty($failedTests)) {
            fwrite(STDERR, "No failed tests found to re-run.\n");

            return 0;
        }

        fwrite(STDERR, 'Running ' . count($failedTests) . " failed tests...\n");

        $command = RerunCommand::build($failedTests);
        fwrite(STDERR, 'Executing: ' . $command . "\n");

        passthru($command, $exitCode);

        return $exitCode;
    }
}
