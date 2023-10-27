<?php

namespace Radish\Consumer;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\Mock;
use Radish\Broker\QueueCollection;
use Radish\Broker\QueueLoader;
use Radish\Middleware\MiddlewareInterface;
use Radish\Middleware\MiddlewareLoader;

class ConsumerFactoryTest extends MockeryTestCase
{
    /**
     * @var Mock|QueueLoader
     */
    public $queueLoader;
    /**
     * @var Mock|MiddlewareLoader
     */
    public $middlewareLoader;
    /**
     * @var ConsumerFactory
     */
    public $factory;

    public function setUp(): void
    {
        $this->queueLoader = Mockery::mock(QueueLoader::class);
        $this->middlewareLoader = Mockery::mock(MiddlewareLoader::class);

        $this->factory = new ConsumerFactory($this->queueLoader, $this->middlewareLoader);
    }

    public function testCreate(): void
    {
        $queueNames = ['test_queue'];
        $middlewareOptions = ['options'];

        $this->queueLoader->shouldReceive('load')
            ->with($queueNames)
            ->once()
            ->andReturn(Mockery::mock(QueueCollection::class));

        $this->middlewareLoader->shouldReceive('load')
            ->with($middlewareOptions)
            ->once()
            ->andReturn([Mockery::mock(MiddlewareInterface::class)]);

        $consumer = $this->factory->create($queueNames, $middlewareOptions, []);

        $this->assertInstanceOf('Radish\Consumer\Consumer', $consumer);
    }
}
