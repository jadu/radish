<?php

namespace Radish\Middleware;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Radish\Broker\Message;
use Radish\Broker\Queue;

class NextTest extends MockeryTestCase
{
    public function testCallsWorkerWhenNoMiddleware(): void
    {
        $message = Mockery::mock(Message::class);
        $queue = Mockery::mock(Queue::class);

        $workerCalled = false;
        $worker = function () use (&$workerCalled) {
            $workerCalled = true;
        };

        $next = new Next([], $worker);
        $next($message, $queue);

        $this->assertTrue($workerCalled);
    }

    public function testCallsMiddlewareInOrder(): void
    {
        $message = Mockery::mock(Message::class);
        $queue = Mockery::mock(Queue::class);

        $callees = [];

        $middlewares[] = function ($message, $queue, $next) use (&$callees) {
            $callees[] = 'middleware1';
            $next($message, $queue);
        };

        $middlewares[] = function ($message, $queue, $next) use (&$callees) {
            $callees[] = 'middleware2';
            $next($message, $queue);
        };

        $worker = function () use (&$callees) {
            $callees[] = 'worker';
        };

        $next = new Next($middlewares, $worker);
        $next($message, $queue);

        $this->assertEquals(['middleware1', 'middleware2', 'worker'], $callees);
    }
}
