<?php

declare(strict_types=1);

namespace PhpUnitRunFailed;

use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;

final class ExecutionFinishedTracker implements ExecutionFinishedSubscriber
{
    public function notify(ExecutionFinished $event): void
    {
        $failedTests = TestResultCollector::getFailedTests();
        $wasRunningFailedTests = $this->isRunningFailedTests();

        $storage = new FailureStorage();

        if (empty($failedTests)) {
            if ($wasRunningFailedTests) {
                fwrite(STDERR, "\nAll previously failed tests now pass! Clearing failure records.\n");
                $storage->clearFailedTests();
            } else {
                $storage->clearFailedTests();
            }
            return;
        }

        $storage->saveFailedTests($failedTests);

        if ($wasRunningFailedTests) {
            fwrite(STDERR, "\n" . count($failedTests) . " test(s) still failing after re-run.\n");
        } else {
            fwrite(STDERR, "\n" . count($failedTests) . " test(s) failed. Use --testsuite=failed to re-run only failed tests:\n");
            fwrite(STDERR, "  vendor/bin/phpunit --testsuite=failed\n");
        }
    }

    private function isRunningFailedTests(): bool
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

}
