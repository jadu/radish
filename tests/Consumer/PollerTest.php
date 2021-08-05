<?php

namespace Radish\Consumer;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\Mock;
use PHPUnit_Framework_TestCase;
use Radish\Broker\Message;
use Radish\Broker\Queue;
use Radish\Broker\QueueCollection;

class PollerTest extends MockeryTestCase
{
    /**
     * @var Mock|QueueCollection
     */
    private $queues;
    /**
     * @var Mock|Message
     */
    private $message;
    /**
     * @var Mock|Queue
     */
    private $queue;

    public function setUp(): void
    {
        $this->message = Mockery::mock(Message::class, [
            'getRoutingKey' => 'abc'
        ]);
        $this->queue = Mockery::mock(Queue::class, [
            'getName' => 'abc'
        ]);
        $this->queues = Mockery::mock(QueueCollection::class, [
            'consume' => null,
            'pop' => $this->message,
            'get' => $this->queue,
        ]);
    }

    public function testConsume(): void
    {
        $this->queues->shouldReceive('pop')
            ->andReturn(
                $this->message,
                null
            );

        $this->queues->shouldReceive('get')
            ->with('abc')
            ->once()
            ->andReturn($this->queue);

        $workerCalled = false;

        $workers = [
            'abc' => function () use (&$workerCalled) {
                $workerCalled = true;
                return false;
            }
        ];

        $consumer = new Poller($this->queues, [], $workers);
        $consumer->consume();

        $this->assertTrue($workerCalled);
    }
}
