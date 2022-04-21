<?php

namespace Radish\Middleware\ExceptionCatcher;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Radish\Broker\Message;
use Radish\Broker\Queue;

class ExceptionCatcherMiddlewareTest extends MockeryTestCase
{
    public $middleware;

    public function setUp(): void
    {
        $this->middleware = new ExceptionCatcherMiddleware();
    }

    /**
     * @dataProvider returnProvider
     */
    public function testWhenNoExceptions($return): void
    {
        $message = Mockery::mock(Message::class);
        $queue = Mockery::mock(Queue::class);

        $next = function () use ($return) {
            return $return;
        };

        $middleware = $this->middleware;
        $this->assertEquals($return, $middleware($message, $queue, $next));
    }

    public function returnProvider(): array
    {
        return [
            [true],
            [false]
        ];
    }

    public function testCatchesExceptions(): void
    {
        $message = Mockery::mock(Message::class);
        $queue = Mockery::mock(Queue::class);

        $next = function () {
            throw new \RuntimeException();
        };

        $middleware = $this->middleware;
        $middleware($message, $queue, $next);

        static::addToAssertionCount(1);
    }
}
