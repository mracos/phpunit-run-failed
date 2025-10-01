<?php

declare(strict_types=1);

namespace PhpUnitRunFailed;

use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;

final class ErroredTestTracker implements ErroredSubscriber
{
    public function notify(Errored $event): void
    {
        $test = $event->test();
        $testId = $this->getTestIdentifier($test);

        $testData = [
            'class' => $test->className(),
            'method' => $test->methodName(),
            'file' => $test->file(),
            'line' => $test->line(),
            'error' => $event->throwable()->message(),
        ];

        TestResultCollector::addFailedTest($testId, $testData);
    }

    private function getTestIdentifier(TestMethod $test): string
    {
        return $test->className() . '::' . $test->methodName();
    }
}
