<?php

namespace Radish\Producer;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Radish\Broker\Exchange;
use Radish\Broker\ExchangeRegistry;

class ProducerFactoryTest extends MockeryTestCase
{
    public $exchangeRegistry;
    public $factory;

    public function setUp(): void
    {
        $this->exchangeRegistry = Mockery::mock(ExchangeRegistry::class);
        $this->factory = new ProducerFactory($this->exchangeRegistry);
    }

    public function testCreate(): void
    {
        $exchange = Mockery::mock(Exchange::class);

        $this->exchangeRegistry->shouldReceive('get')
            ->with('exchange_name')
            ->andReturn($exchange)
            ->once();

        $producer = $this->factory->create('exchange_name');

        $this->assertInstanceOf(ProducerInterface::class, $producer);
    }
}
