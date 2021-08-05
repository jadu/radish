<?php

namespace Radish\Middleware\Retry;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Radish\Broker\Exchange;
use Radish\Broker\ExchangeRegistry;
use Radish\Broker\Message;
use Radish\Broker\Queue;
use RuntimeException;

class RetryMiddlewareTest extends MockeryTestCase
{
    public $exchange;
    public $exchangeRegistry;
    public $middleware;

    public function setUp(): void
    {
        $this->exchange = Mockery::mock(Exchange::class);
        $this->exchangeRegistry = Mockery::mock(ExchangeRegistry::class, [
            'get' => $this->exchange
        ]);

        $this->middleware = new RetryMiddleware($this->exchangeRegistry);
        $this->middleware->setOptions([
            'exchange' => 'test'
        ]);
    }

    /**
     * @dataProvider returnProvider
     */
    public function testWhenNoExceptions($return): void
    {
        $message = Mockery::mock(Message::class);
        $queue = Mockery::mock(Queue::class);

        $next = function () use ($return) {
            return $return;
        };

        $middleware = $this->middleware;
        $this->assertEquals($return, $middleware($message, $queue, $next));
    }

    public function returnProvider()
    {
        return [
            [true],
            [false]
        ];
    }

    public function testRepublishesMessageWithExpiration(): void
    {
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');

        $queue = Mockery::mock(Queue::class);

        $next = function () {
            throw new \RuntimeException();
        };

        $this->exchange->shouldReceive('publish')
            ->with('body', 'key', AMQP_NOPARAM, Mockery::on(function ($attributes) {
                return isset($attributes['expiration']) && $attributes['expiration'] > 0;
            }))
            ->once();
        $this->expectException(RuntimeException::class);

        $middleware = $this->middleware;
        $middleware($message, $queue, $next);
    }

    public function testRemovesXDeathHeaderBeforeRepublishing(): void
    {
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeader('x-death', []);

        $queue = Mockery::mock(Queue::class);

        $next = function () {
            throw new \RuntimeException();
        };

        $this->exchange->shouldReceive('publish')
            ->with('body', 'key', AMQP_NOPARAM, Mockery::on(function ($attributes) {
                return !isset($attributes['headers']['x-death']);
            }))
            ->once();
        $this->expectException(RuntimeException::class);


        $middleware = $this->middleware;
        $middleware($message, $queue, $next);
    }

    public function testSetsRetryAttemptHeader(): void
    {
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeader('x-death', []);

        $queue = Mockery::mock(Queue::class);

        $next = function () {
            throw new \RuntimeException();
        };

        $this->exchange->shouldReceive('publish')
            ->with('body', 'key', AMQP_NOPARAM, Mockery::on(function ($attributes) {
                return isset($attributes['headers']['retry_attempts']);
            }))
            ->once();
        $this->expectException(RuntimeException::class);

        $middleware = $this->middleware;
        $middleware($message, $queue, $next);
    }

    public function testIncrementsRetryAttemptHeader(): void
    {
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeader('retry_attempts', 3);

        $queue = Mockery::mock(Queue::class);

        $next = function () {
            throw new \RuntimeException();
        };

        $this->exchange->shouldReceive('publish')
            ->with('body', 'key', AMQP_NOPARAM, Mockery::on(function ($attributes) {
                return $attributes['headers']['retry_attempts'] === 4;
            }))
            ->once();
        $this->expectException(RuntimeException::class);

        $middleware = $this->middleware;
        $middleware($message, $queue, $next);
    }

    public function testDoesNotRepublishIfRetryMaxAttemptsReached(): void
    {
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeader('retry_attempts', 5);
        $message->setHeader('retry_options', [
            'max_attempts' => 5
        ]);

        $queue = Mockery::mock(Queue::class);

        $next = function () {
            throw new \RuntimeException();
        };

        $this->exchange->shouldReceive('publish')->never();
        static::expectException(RuntimeException::class);

        $middleware = $this->middleware;
        $middleware($message, $queue, $next);
    }

    public function testDoesNotRetryIfMaxAttemptsIsZero(): void
    {
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeader('retry_options', [
            'max_attempts' => 0
        ]);

        $queue = Mockery::mock(Queue::class);

        $next = function () {
            throw new \RuntimeException();
        };

        $this->exchange->shouldReceive('publish')->never();
        $this->expectException(RuntimeException::class);

        $middleware = $this->middleware;
        $middleware($message, $queue, $next);
    }
}
