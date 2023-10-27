<?php

namespace Radish\Middleware\MemoryLimit;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Psr\Log\LoggerInterface;
use Radish\Broker\Message;
use Radish\Broker\Queue;

class MemoryLimitMiddlewareTest extends MockeryTestCase
{
    public $logger;
    public $message;
    public $queue;

    public function setUp(): void
    {
        $this->logger = Mockery::mock(LoggerInterface::class, [
            'info' => null
        ]);

        $this->message = Mockery::mock(Message::class);
        $this->queue = Mockery::mock(Queue::class);
    }

    public function returnProvider()
    {
        return [
            [true],
            [false]
        ];
    }

    /**
     * @dataProvider returnProvider
     */
    public function testWhenNoMemoryLimit($return): void
    {
        $middleware = new MemoryLimitMiddleware($this->logger);

        $this->assertEquals($return, $middleware($this->message, $this->queue, function () use ($return) {
            return $return;
        }));
    }

    public function testWhenMemoryLimitNotExceeded(): void
    {
        $middleware = new MemoryLimitMiddleware($this->logger);
        $middleware->setOptions([
            'limit' => 51
        ]);

        $this->assertEquals(true, $middleware($this->message, $this->queue, function () {
            return true;
        }));
    }

    public function testWhenMemoryLimitExceeded(): void
    {
        $middleware = new MemoryLimitMiddleware($this->logger);
        $middleware->setOptions([
            'limit' => 49
        ]);

        $this->assertEquals(false, $middleware($this->message, $this->queue, function () {
            return true;
        }));
    }

    public function testWhenMemoryLimitReached(): void
    {
        $middleware = new MemoryLimitMiddleware($this->logger);
        $middleware->setOptions([
            'limit' => 50
        ]);

        $this->assertEquals(false, $middleware($this->message, $this->queue, function () {
            return true;
        }));
    }
}

/**
 * Stub the memory_get_usage function within the current namespace
 */
function memory_get_usage()
{
    return 52428800; // 50 MB
}
