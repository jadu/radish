<?php

namespace Radish\Middleware;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use RuntimeException;

class MiddlewareRegistryTest extends MockeryTestCase
{
    public $container;
    public $registry;

    public function setUp(): void
    {
        $this->container = Mockery::mock('Symfony\Component\DependencyInjection\ContainerInterface');
        $this->registry = new MiddlewareRegistry($this->container);
    }

    public function testGet(): void
    {
        $middleware = Mockery::mock(MiddlewareInterface::class);

        $this->container->shouldReceive('has')
            ->with('service_name')
            ->andReturn(true);

        $this->container->shouldReceive('get')
            ->with('service_name')
            ->andReturn($middleware);

        $this->registry->register('middleware_name', 'service_name');

        $this->assertSame($middleware, $this->registry->get('middleware_name'));
    }

    public function testGetThrowsExceptionWhenMiddlewareNotRegistered(): void
    {
        $middleware = Mockery::mock(MiddlewareInterface::class);

        $this->expectException(RuntimeException::class);

        $this->assertSame($middleware, $this->registry->get('middleware_name'));
    }

    public function testGetThrowsExceptionWhenMiddlewareServiceNotDefined(): void
    {
        $middleware = Mockery::mock(MiddlewareInterface::class);

        $this->container->shouldReceive('has')
            ->andReturn(false);
        $this->expectException(RuntimeException::class);

        $this->registry->register('middleware_name', 'service_name');

        $this->assertSame($middleware, $this->registry->get('middleware_name'));
    }
}
