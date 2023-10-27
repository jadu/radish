<?php

namespace Radish\Consumer;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Radish\Broker\Message;
use Radish\Broker\Queue;
use Radish\Broker\QueueCollection;
use Radish\Middleware\InitializableInterface;
use RuntimeException;

class ConsumerTest extends MockeryTestCase
{
    public $queues;

    public function setUp(): void
    {
        $this->queues = Mockery::mock(QueueCollection::class, [
            'consume' => null
        ]);
    }

    public function testConsume()
    {
        $consumer = new Consumer($this->queues, [], []);

        $this->queues->shouldReceive('consume')
            ->with([$consumer, 'process'])
            ->once();

        $consumer->consume();
    }

    public function testConsumeShouldInitializeMiddleware(): void
    {
        $middleware = Mockery::mock(InitializableInterface::class);
        $middleware->shouldReceive('initialize')
            ->once();

        $consumer = new Consumer($this->queues, [$middleware], []);
        $consumer->consume();
    }

    public function testProcess(): void
    {
        $queueName = 'test_message';

        $queue = Mockery::mock(Queue::class, [
            'getName' => $queueName
        ]);

        $this->queues->shouldReceive('get')
            ->with($queueName)
            ->andReturn($queue);

        $message = Mockery::mock(Message::class, [
            'getRoutingKey' => $queueName
        ]);

        $workerCalled = false;

        $workers = [
            $queueName => function () use (&$workerCalled) {
                $workerCalled = true;
            }
        ];

        $consumer = new Consumer($this->queues, [], $workers);
        $consumer->process($message);

        $this->assertTrue($workerCalled);
    }

    public function testProcessWhenWorkerNotAvailable(): void
    {
        $queueName = 'test_message';
        $queue = Mockery::mock(Queue::class, [
            'getName' => $queueName
        ]);
        $message = Mockery::mock(Message::class, [
            'getRoutingKey' => $queueName
        ]);
        $consumer = new Consumer($this->queues, [], []);

        $this->queues->shouldReceive('get')
            ->with($queueName)
            ->andReturn($queue);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Worker not defined for queue');


        $consumer->process($message);
    }
}
