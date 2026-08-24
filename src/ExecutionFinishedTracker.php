<?php

declare(strict_types=1);

namespace PhpUnitRunFailed;

use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;

final class ExecutionFinishedTracker implements ExecutionFinishedSubscriber
{
    private FailureStorage $storage;

    /** @var resource */
    private $output;

    /**
     * @param resource|null $output defaults to STDERR
     */
    public function __construct(?FailureStorage $storage = null, $output = null)
    {
        $this->storage = $storage ?? new FailureStorage();
        $this->output = $output ?? STDERR;
    }

    public function notify(ExecutionFinished $event): void
    {
        $failedTests = TestResultCollector::getFailedTests();
        $wasRunningFailedTests = RerunCommand::isFailedTestsuiteRun();

        if (empty($failedTests)) {
            if ($wasRunningFailedTests) {
                fwrite($this->output, "\nAll previously failed tests now pass! Clearing failure records.\n");
            }

            $this->storage->clearFailedTests();

            return;
        }

        $this->storage->saveFailedTests($failedTests);

        if ($wasRunningFailedTests) {
            fwrite($this->output, "\n" . count($failedTests) . " test(s) still failing after re-run.\n");

            return;
        }

        fwrite($this->output, "\n" . count($failedTests) . " test(s) failed. Use --testsuite=failed to re-run only failed tests:\n");
        fwrite($this->output, "  vendor/bin/phpunit --testsuite=failed\n");
    }
}
