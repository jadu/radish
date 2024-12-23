<?php

namespace Radish\Middleware\Logger;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\Mock;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Radish\Broker\Message;
use Radish\Broker\Queue;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoggerMiddlewareTest extends MockeryTestCase
{
    /**
     * @var Mock|LoggerInterface
     */
    private $logger;
    /**
     * @var Mock|Message
     */
    private $message;
    /**
     * @var LoggerMiddleware
     */
    private $middleware;
    /**
     * @var OptionsResolver
     */
    private $optionsResolver;
    /**
     * @var Mock|Queue
     */
    private $queue;

    public function setUp(): void
    {
        $this->logger = Mockery::mock(LoggerInterface::class, [
            'log' => null,
        ]);
        $this->message = Mockery::mock(Message::class, [
            'getBody' => 'my message content',
            'getExchangeName' => 'my_exchange',
            'getHeaders' => ['x-header-one' => '1', 'x-header-two' => '2'],
            'getRoutingKey' => 'my.routing.key',
        ]);
        $this->optionsResolver = new OptionsResolver();
        $this->queue = Mockery::mock(Queue::class, [
            'getName' => 'test',
            'ack' => null,
            'nack' => null,
        ]);

        $this->middleware = new LoggerMiddleware($this->logger);
        $this->middleware->setOptions(
            [
                'log_level' => 1,
                'log_message' => 'non-null message'
            ]
        );
    }

    public function testInvokeWithDefaultOptionsLogsCorrectly(): void
    {
        $next = function () {
            return true;
        };
        $this->middleware->configureOptions($this->optionsResolver);
        $this->middleware->setOptions($this->optionsResolver->resolve());

        $this->logger->shouldReceive('log')
            ->with(LoggerMiddleware::DEFAULT_LOG_LEVEL, LoggerMiddleware::DEFAULT_LOG_MESSAGE, [
                'body' => 'my message content',
                'exchange_name' => 'my_exchange',
                'headers' => ['x-header-one' => '1', 'x-header-two' => '2'],
                'routing_key' => 'my.routing.key'
            ])
            ->once();

        $this->middleware->__invoke($this->message, $this->queue, $next);
    }

    public function testInvokeWithCustomOptionsLogsCorrectly(): void
    {
        $next = function () {
            return true;
        };
        $this->middleware->configureOptions($this->optionsResolver);
        $this->middleware->setOptions($this->optionsResolver->resolve([
            'log_level' => LogLevel::CRITICAL,
            'log_message' => 'Doing a thing'
        ]));

        $this->logger->shouldReceive('log')
            ->with(LogLevel::CRITICAL, 'Doing a thing', [
                'body' => 'my message content',
                'exchange_name' => 'my_exchange',
                'headers' => ['x-header-one' => '1', 'x-header-two' => '2'],
                'routing_key' => 'my.routing.key'
            ])
            ->once();

        $this->middleware->__invoke($this->message, $this->queue, $next);
    }

    public function testInvokePassesCorrectArgsToNext(): void
    {
        $this->logger->shouldReceive('log');

        $next = function ($message, $queue) {
            self::assertSame($this->message, $message);
            self::assertSame($this->queue, $queue);
        };

        $this->middleware->__invoke($this->message, $this->queue, $next);
    }

    public function testInvokeCallsTheMiddlewareAfterLogging(): void
    {
        $trace = [];
        $next = function () use (&$trace) {
            $trace[] = 'next()';
        };
        $this->logger->shouldReceive('log')
            ->andReturnUsing(function () use (&$trace) {
                $trace[] = 'log()';
            });

        $this->middleware->__invoke($this->message, $this->queue, $next);

        self::assertEquals(['log()', 'next()'], $trace);
    }

    /**
     * @dataProvider booleanProvider
     * @param bool $boolean
     */
    public function testInvokeReturnsTheResultFromNextMiddleware($boolean)
    {
        $next = function () use ($boolean) {
            return $boolean;
        };

        self::assertSame($boolean, $this->middleware->__invoke($this->message, $this->queue, $next));
    }

    /**
     * @return array
     */
    public function booleanProvider()
    {
        return [
            [true],
            [false]
        ];
    }
}
