<?php

declare(strict_types=1);

namespace TestProject\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testThatPass(): void
    {
        $this->assertEquals(10, 5 + 5);
    }

    public function testWithExceptionThatPass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        throw new InvalidArgumentException('Division by zero');
    }

    // uncomment what makes this test pass to test the rerun
    public function testThatFails(): void
    {
        $a = 15;
        $b = 10;
        /* $b += 5; */
        $this->assertEquals($a, $b);
    }

    public function testWithException(): void
    {
        /* $this->expectException(InvalidArgumentException::class); */
        throw new InvalidArgumentException('Division by zero');
    }

    public function testErrorTest(): void
    {
        trigger_error('This is a test error', E_USER_ERROR);
        /* $this->expectNotToPerformAssertions(); */
    }

}
