<?php

namespace Radish\Middleware\Ack;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Psr\Log\LoggerInterface;
use Radish\Broker\Message;
use Radish\Broker\Queue;
use RuntimeException;

class AckMiddlewareTest extends MockeryTestCase
{
    public $logger;
    public $middleware;
    public $message;
    public $queue;

    public function setUp(): void
    {
        $this->logger = Mockery::mock(LoggerInterface::class, [
            'info' => null,
            'warning' => null,
        ]);

        $this->middleware = new AckMiddleware();

        $this->message = Mockery::mock(Message::class, [
            'getDeliveryTag' => '1'
        ]);

        $this->queue = Mockery::mock(Queue::class, [
            'getName' => 'test',
            'ack' => null,
            'nack' => null,
        ]);
    }

    /**
     * @dataProvider returnProvider
     */
    public function testAckWhenNoExceptions($return): void
    {
        $this->queue->shouldReceive('ack')->with($this->message)->once();

        $next = function () use ($return) {
            return $return;
        };

        $middleware = $this->middleware;
        $this->assertEquals($return, $middleware($this->message, $this->queue, $next));
    }

    public function returnProvider(): array
    {
        return [
            [true],
            [false]
        ];
    }

    public function testAckWhenNoExceptionsLogsInfo(): void
    {
        $this->logger->shouldReceive('info')
            ->with('Message #1 from queue "test" has been acknowledged', [
                'middleware' => 'ack'
            ])
            ->once();

        $next = function () {
            return true;
        };

        $middleware = new AckMiddleware($this->logger);
        $middleware($this->message, $this->queue, $next);
    }

    public function testNackWhenExceptionCaught(): void
    {
        $next = function () {
            throw new RuntimeException();
        };

        $this->queue->shouldReceive('nack')->with($this->message, false)->once();
        $this->expectException(RuntimeException::class);

        $middleware = $this->middleware;
        $middleware($this->message, $this->queue, $next);
    }

    public function testNackWhenExceptionCaughtLogsException(): void
    {
        $exception = new RuntimeException();

        $this->logger->shouldReceive('warning')
            ->with('Exception caught and message #1 from queue "test" negatively acknowledged', [
                'middleware' => 'ack',
                'exception' => $exception,
            ])
            ->once();
        $this->expectException(RuntimeException::class);

        $next = function () use ($exception) {
            throw $exception;
        };

        $middleware = new AckMiddleware($this->logger);
        $middleware($this->message, $this->queue, $next);
    }
}
