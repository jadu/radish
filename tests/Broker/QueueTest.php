<?php

namespace Radish\Broker;

use AMQPEnvelope;
use AMQPQueue;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\Mock;

class QueueTest extends MockeryTestCase
{
    /**
     * @var Queue
     */
    private $queue;
    /**
     * @var Mock|Connection
     */
    private $connection;
    /**
     * @var Mock|AMQPQueue
     */
    private $amqpQueue;

    public function setUp(): void
    {
        $this->amqpQueue = Mockery::mock('AMQPQueue', [
            'declareQueue' => 0,
            'setName' => null,
            'setFlags' => null,
        ]);

        $this->connection = Mockery::mock(Connection::class, [
            'createQueue' => $this->amqpQueue,
        ]);

        $this->queue = new Queue($this->connection, 'test_queue', true, []);
    }

    public function testDeclareQueueSetsMaxPriorityArgOnAmqpQueue(): void
    {
        $this->queue->setMaxPriority(100);

        $this->amqpQueue->shouldReceive('setArgument')
            ->with('x-max-priority', 100)
            ->once();

        $this->queue->declareQueue();
    }

    public function testDeclareQueueDoesntSetMaxPriorityOnAmqpWhenNull(): void
    {
        $this->queue->setMaxPriority(null);

        $this->amqpQueue->shouldReceive('setArgument')
            ->never();

        $this->queue->declareQueue();
    }

    public function testPopReturnsNullWhenNoMessages()
    {
        $this->amqpQueue->shouldReceive('get')
            ->andReturn(null)
            ->once();

        static::assertNull($this->queue->pop());
    }

    public function testPopReturnsMessageWhenMessageInQueue(): void
    {
        $this->amqpQueue->shouldReceive('get')
            ->andReturn(Mockery::mock(new AMQPEnvelope()))
            ->once();

        $message = $this->queue->pop();

        static::assertInstanceOf(Message::class, $message);
    }
}
