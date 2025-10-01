<?php

declare(strict_types=1);

namespace PhpUnitRunFailed;

use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;

final class FailedTestTracker implements FailedSubscriber
{
    public function notify(Failed $event): void
    {
        $test = $event->test();
        $testId = $this->getTestIdentifier($test);

        $testData = [
            'class' => $test->className(),
            'method' => $test->methodName(),
            'file' => $test->file(),
            'line' => $test->line(),
            'failure' => $event->throwable()->message(),
        ];

        TestResultCollector::addFailedTest($testId, $testData);
    }

    private function getTestIdentifier(TestMethod $test): string
    {
        return $test->className() . '::' . $test->methodName();
    }

}
