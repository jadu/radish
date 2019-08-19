<?php

namespace Radish\Middleware\Logger;

use Mockery;
use Mockery\Mock;
use PHPUnit_Framework_TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Radish\Broker\Message;
use Radish\Broker\Queue;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoggerMiddlewareTest extends PHPUnit_Framework_TestCase
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

    public function setUp()
    {
        $this->logger = Mockery::mock('Psr\Log\LoggerInterface', [
            'log' => null,
        ]);
        $this->message = Mockery::mock('Radish\Broker\Message', [
            'getBody' => 'my message content',
            'getExchangeName' => 'my_exchange',
            'getHeaders' => ['x-header-one' => '1', 'x-header-two' => '2'],
            'getRoutingKey' => 'my.routing.key',
        ]);
        $this->optionsResolver = new OptionsResolver();
        $this->queue = Mockery::mock('Radish\Broker\Queue', [
            'getName' => 'test',
            'ack' => null,
            'nack' => null,
        ]);

        $this->middleware = new LoggerMiddleware($this->logger);
    }

    public function testInvokeWithDefaultOptionsLogsCorrectly()
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

    public function testInvokeWithCustomOptionsLogsCorrectly()
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

    public function testInvokePassesCorrectArgsToNext()
    {
        $this->logger->shouldReceive('log');

        $next = function ($message, $queue) {
            self::assertSame($this->message, $message);
            self::assertSame($this->queue, $queue);
        };

        $this->middleware->__invoke($this->message, $this->queue, $next);
    }

    public function testInvokeCallsTheMiddlewareAfterLogging()
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
}
