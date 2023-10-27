<?php

namespace Radish\Producer;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Radish\Broker\Exchange;
use Radish\Broker\Message;

class ProducerTest extends MockeryTestCase
{
    public $exchange;
    public $producer;

    public function setUp(): void
    {
        $this->exchange = Mockery::mock(Exchange::class);
        $this->producer = new Producer($this->exchange);
    }

    public function testPublish(): void
    {
        $message = new Message();
        $message->setBody('test');
        $message->setRoutingKey('routing');

        $this->exchange->shouldReceive('publish')
            ->with(
                $message->getBody(),
                $message->getRoutingKey(),
                AMQP_NOPARAM,
                $message->getAttributes()
            )
            ->once();

        $this->producer->publish($message);
    }
}
