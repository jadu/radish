<?php

namespace Radish\Middleware\Retry;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\Mock;
use Psr\Log\LoggerInterface;
use Radish\Broker\Exchange;
use Radish\Broker\ExchangeInterface;
use Radish\Broker\ExchangeRegistry;
use Radish\Broker\Message;
use Radish\Broker\Queue;
use RuntimeException;

class NonBlockingRetryMiddlewareTest extends MockeryTestCase
{
    /**
     * @var Mock|ExchangeInterface
     */
    public $exchange;
    /**
     * @var Mock|ExchangeRegistry
     */
    public $exchangeRegistry;
    /**
     * @var NonBlockingRetryMiddleware
     */
    public $middleware;
    /**
     * @var Mock|LoggerInterface
     */
    private $logger;

    public function setUp(): void
    {
        $this->exchange = Mockery::mock(Exchange::class, [
            'publish' => null,
        ]);
        $this->exchangeRegistry = Mockery::mock(ExchangeRegistry::class, [
            'get' => $this->exchange,
        ]);
        $this->logger = Mockery::mock(LoggerInterface::class, [
            'info' => null,
            'critical' => null,
        ]);

        $this->middleware = new NonBlockingRetryMiddleware($this->exchangeRegistry, $this->logger);
        $this->middleware->setOptions([
            'exchange' => 'test',
        ]);
    }

    /**
     * @dataProvider returnProvider
     */
    public function testWhenNoExceptions($return): void
    {
        $message = Mockery::mock(Message::class);
        $message->shouldReceive('getHeader')
            ->andReturnUsing(function ($name, $default) {
                return $default;
            });
        $queue = Mockery::mock(Queue::class);
        $next = function () use ($return) {
            return $return;
        };
        $middleware = $this->middleware;

        $this->assertEquals($return, $middleware($message, $queue, $next));
    }

    public function returnProvider(): array
    {
        return [
            [true],
            [false]
        ];
    }

    public function testSetsRetryAtHeaderForMessagesAlreadyInRetryQueue(): void
    {
        $currentTimestamp = time();
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeader('retry_attempts', 3);
        $message->setHeader('retry_options', [
            'max_attempts' => 5
        ]);
        $queue = Mockery::mock(Queue::class);
        $next = function () {
            return true;
        };
        $middleware = $this->middleware;

        $this->exchange->shouldReceive('publish')->never();

        $middleware($message, $queue, $next);

        $this->assertArrayHasKey('retry_at', $message->getHeaders());
        $this->assertGreaterThanOrEqual($currentTimestamp, $message->getHeader('retry_at'));
    }

    public function testRepublishesMessageWithFixedExpiration(): void
    {
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeader('retry_options', [
            'max_attempts' => 5
        ]);
        $queue = Mockery::mock(Queue::class);
        $next = function () {
            throw new \RuntimeException();
        };
        $middleware = $this->middleware;

        $this->exchange->shouldReceive('publish')
            ->with('body', 'key', AMQP_NOPARAM, Mockery::on(function ($attributes) {
                return isset($attributes['expiration']) && $attributes['expiration'] === 60000;
            }))
            ->once();
        $this->expectException(RuntimeException::class);

        $middleware($message, $queue, $next);
    }

    public function testRepublishesMessageWithRetryHeader(): void
    {
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeader('retry_options', [
            'max_attempts' => 5
        ]);
        $queue = Mockery::mock(Queue::class);
        $next = function () {
            throw new \RuntimeException();
        };
        $middleware = $this->middleware;

        $this->exchange->shouldReceive('publish')
            ->with('body', 'key', AMQP_NOPARAM, Mockery::on(function ($attributes) {
                return isset($attributes['headers']['retry_at']);
            }))
            ->once();
        $this->expectException(RuntimeException::class);

        $middleware($message, $queue, $next);
    }

    public function testRepublishesMessageWithIncreasedRetryInterval(): void
    {
        $currentTimestamp = time();
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeader('retry_attempts', 3);
        $message->setHeader('retry_at', $currentTimestamp);
        $message->setHeader('retry_options', [
            'max_attempts' => 5
        ]);
        $queue = Mockery::mock(Queue::class);
        $next = function () {
            throw new \RuntimeException();
        };
        $middleware = $this->middleware;

        $this->exchange->shouldReceive('publish')
            ->with('body', 'key', AMQP_NOPARAM, Mockery::on(function ($attributes) use ($currentTimestamp) {
                return $attributes['headers']['retry_at'] >= $currentTimestamp + 276 && //lower boundary of back-off calculation
                    $attributes['headers']['retry_at'] <= $currentTimestamp + 421; //upper boundary of back-off calculation
            }))
            ->once();
        $this->expectException(RuntimeException::class);

        $middleware($message, $queue, $next);
    }

    public function testRepublishesMessageWithoutIncreasingRetryIntervalOrRetryCountIfNotReadyToBeProcessed(): void
    {
        $currentTimestamp = time();
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeader('retry_attempts', 3);
        $message->setHeader('retry_at', $currentTimestamp *2); // set far in the future so won't get processed
        $message->setHeader('retry_options', [
            'max_attempts' => 5
        ]);
        $queue = Mockery::mock(Queue::class);
        $next = function () {
            throw new \RuntimeException();
        };
        $middleware = $this->middleware;

        $this->exchange->shouldReceive('publish')
            ->with('body', 'key', AMQP_NOPARAM, Mockery::on(function ($attributes) use ($currentTimestamp) {
                return $attributes['headers']['retry_attempts'] === 3 &&
                    $attributes['headers']['retry_at'] === $currentTimestamp * 2;
            }))
            ->once();

        $middleware($message, $queue, $next);
    }

    public function testRepublishesMessageWithoutLoggingIfNotReadyToBeProcessed(): void
    {
        $currentTimestamp = time();
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeader('retry_attempts', 3);
        $message->setHeader('retry_at', $currentTimestamp *2); // set far in the future so won't get processed
        $message->setHeader('retry_options', [
            'max_attempts' => 5
        ]);
        $queue = Mockery::mock(Queue::class);
        $next = function () {
            throw new \RuntimeException();
        };
        $middleware = $this->middleware;

        $this->logger->shouldReceive('info')->never();

        $middleware($message, $queue, $next);
    }

    public function testRemovesXDeathHeaderBeforeRepublishing(): void
    {
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeader('retry_options', [
            'max_attempts' => 5
        ]);
        $message->setHeader('x-death', []);
        $queue = Mockery::mock(Queue::class);
        $next = function () {
            throw new \RuntimeException();
        };
        $middleware = $this->middleware;

        $this->exchange->shouldReceive('publish')
            ->with('body', 'key', AMQP_NOPARAM, Mockery::on(function ($attributes) {
                return !isset($attributes['headers']['x-death']);
            }))
            ->once();
        $this->expectException(RuntimeException::class);

        $middleware($message, $queue, $next);
    }

    public function testSetsRetryAttemptHeader(): void
    {
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeader('retry_options', [
            'max_attempts' => 5
        ]);
        $message->setHeader('x-death', []);
        $queue = Mockery::mock(Queue::class);
        $next = function () {
            throw new \RuntimeException();
        };
        $middleware = $this->middleware;

        $this->exchange->shouldReceive('publish')
            ->with('body', 'key', AMQP_NOPARAM, Mockery::on(function ($attributes) {
                return isset($attributes['headers']['retry_attempts']) && $attributes['headers']['retry_attempts'] === 1;
            }))
            ->once();
        $this->expectException(RuntimeException::class);

        $middleware($message, $queue, $next);
    }

    public function retryAttemptsDataProvider(): array
    {
        // headers, shouldRepublish
        return [
            [['retry_attempts' => 5, 'retry_options'=> ['max_attempts' => 5]], false],
            [['retry_attempts' => 3, 'retry_options'=> ['max_attempts' => 5]], true],
            [['retry_attempts' => 10], false], // max_attempts defaults to 10
            [['retry_attempts' => 9], true], // max_attempts defaults to 10
            [['retry_options' => ['max_attempts' => 0]], false],
        ];
    }

    /**
     * @dataProvider retryAttemptsDataProvider
     */
    public function testRetryLimitsAreRespected($headers, $shouldRepublish): void
    {
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeaders($headers);
        $queue = Mockery::mock(Queue::class);
        $next = function () {
            throw new \RuntimeException();
        };
        $middleware = $this->middleware;

        if ($shouldRepublish) {
            $this->exchange->shouldReceive('publish')
                ->with('body', 'key', AMQP_NOPARAM, Mockery::on(function ($attributes) use ($headers) {
                    return $attributes['headers']['retry_attempts'] === $headers['retry_attempts'] + 1;
                }))
                ->once();
        } else {
            $this->exchange->shouldReceive('publish')
                ->never();
        }
        $this->expectException(RuntimeException::class);

        $middleware($message, $queue, $next);
    }

    public function testCriticalErrorIsLoggedWhenRetryLimitReached(): void
    {
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeader('retry_attempts', 5);
        $message->setHeader('retry_options', ['max_attempts' => 5]);
        $queue = Mockery::mock(Queue::class);
        $next = function () {
            throw new \RuntimeException();
        };
        $middleware = $this->middleware;

        $this->logger->shouldReceive('critical')
            ->once()
            ->with('Failed to process message after 5 retries');
        $this->expectException(RuntimeException::class);

        $middleware($message, $queue, $next);
    }

    public function testInfoMessageIsLoggedWhenMessageRequeuedDueToRetryFailure(): void
    {
        $message = new Message();
        $message->setBody('body');
        $message->setRoutingKey('key');
        $message->setHeader('retry_attempts', 4);
        $message->setHeader('retry_options', ['max_attempts' => 5]);
        $queue = Mockery::mock(Queue::class);
        $next = function () {
            throw new \RuntimeException();
        };
        $middleware = $this->middleware;

        $this->logger->shouldReceive('info')
            ->once()
            ->with(Mockery::on(
                function ($message) {
                    return preg_match('/Retrying message in \d+ seconds \(attempt 5\)/', $message) === 1;
                }

            ));
        $this->expectException(RuntimeException::class);

        $middleware($message, $queue, $next);
    }
}
