<?php

namespace Radish\Middleware;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\Mock;
use PHPUnit_Framework_TestCase;

class MiddlewareLoaderTest extends MockeryTestCase
{
    /**
     * @var Mock|MiddlewareRegistry
     */
    private $middlewareRegistry;
    /**
     * @var MiddlewareLoader
     */
    private $loader;

    public function setUp(): void
    {
        $this->middlewareRegistry = Mockery::mock(MiddlewareRegistry::class);
        $this->loader = new MiddlewareLoader($this->middlewareRegistry);
    }

    public function testLoadWithConfigurableMiddleware(): void
    {
        $middlewareOptions = [
            'max_messages' => [
                'limit' => 10
            ]
        ];

        $middleware = Mockery::mock(ConfigurableInterface::class);

        $middleware->shouldReceive('configureOptions')
            ->andReturnUsing(function ($resolver) {
                $resolver->setDefaults([
                    'limit' => 5
                ]);
            })
            ->once();

        $middleware->shouldReceive('setOptions')
            ->with($middlewareOptions['max_messages'])
            ->once();

        $this->middlewareRegistry->shouldReceive('get')
            ->with('max_messages')
            ->andReturn($middleware)
            ->once();

        $this->loader->load($middlewareOptions);
    }

    public function testLoadWithConfigurableMiddlewareWhenOptionsNotProvided(): void
    {
        $middlewareOptions = [
            'max_messages' => true
        ];

        $middleware = Mockery::mock(ConfigurableInterface::class);

        $middleware->shouldReceive('configureOptions')
            ->andReturnUsing(function ($resolver) {
                $resolver->setDefaults([
                    'limit' => 5
                ]);
            })
            ->once();

        $middleware->shouldReceive('setOptions')
            ->with($middlewareOptions['max_messages'])
            ->once();

        $this->middlewareRegistry->shouldReceive('get')
            ->with('max_messages')
            ->andReturn($middleware)
            ->once();

        $this->loader->load($middlewareOptions);
    }
}
