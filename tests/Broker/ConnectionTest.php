<?php

namespace Radish\Broker;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class ConnectionTest extends MockeryTestCase
{
    public $amqpConnection;
    public $amqpChannel;
    public $amqpExchange;
    public $amqpQueue;
    public $amqpFactory;
    public $credentials;
    public $connection;

    public function setUp(): void
    {
        $this->amqpConnection = Mockery::mock('AMQPConnection', [
            'connect' => null
        ]);

        $this->amqpChannel = Mockery::mock('AMQPChannel');
        $this->amqpExchange = Mockery::mock('AMQPExchange');
        $this->amqpQueue = Mockery::mock('AMQPQueue');

        $this->amqpFactory = Mockery::mock(AMQPFactory::class, [
            'createConnection' => $this->amqpConnection,
            'createChannel' => $this->amqpChannel,
            'createExchange' => $this->amqpExchange,
            'createQueue' => $this->amqpQueue
        ]);

        $this->credentials = [
            'password' => 'test'
        ];

        $this->connection = new Connection($this->amqpFactory, $this->credentials);
    }

    public function testConnect(): void
    {
        $this->amqpFactory->shouldReceive('createConnection')
            ->with($this->credentials)
            ->andReturn($this->amqpConnection)
            ->once();

        $this->amqpConnection->shouldReceive('connect')
            ->once();

        $this->connection->connect();
    }

    public function testDeconstructDisconnects(): void
    {
        $this->amqpConnection->shouldReceive('disconnect')->once();

        $this->connection->connect();
        $this->connection->__deconstruct();
    }

    public function testIsConnectedWhenConnected(): void
    {
        $this->connection->connect();

        $this->assertTrue($this->connection->isConnected());
    }

    public function testIsConnectedWhenNotConnected(): void
    {
        $this->assertFalse($this->connection->isConnected());
    }

    public function testGetChannel(): void
    {
        $this->amqpFactory->shouldReceive('createChannel')
            ->with($this->amqpConnection)
            ->andReturn($this->amqpChannel)
            ->once();

        $this->assertSame($this->amqpChannel, $this->connection->getChannel());
        $this->assertTrue($this->connection->isConnected());
    }

    public function testCreateExchange(): void
    {
        $this->amqpFactory->shouldReceive('createExchange')
            ->with($this->amqpChannel)
            ->andReturn($this->amqpExchange)
            ->once();

        $this->assertSame($this->amqpExchange, $this->connection->createExchange());
    }

    public function testCreateQueue(): void
    {
        $this->amqpFactory->shouldReceive('createQueue')
            ->with($this->amqpChannel)
            ->andReturn($this->amqpQueue)
            ->once();

        $this->assertSame($this->amqpQueue, $this->connection->createQueue());
    }
}
