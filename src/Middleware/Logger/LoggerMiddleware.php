<?php

namespace Radish\Middleware\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Radish\Broker\Message;
use Radish\Broker\Queue;
use Radish\Middleware\ConfigurableInterface;
use Radish\Middleware\MiddlewareInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Logs details about the Radish message currently being processed.
 */
class LoggerMiddleware implements ConfigurableInterface, MiddlewareInterface
{
    const DEFAULT_LOG_LEVEL = LogLevel::DEBUG;
    const DEFAULT_LOG_MESSAGE = 'Processing message';

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var string
     */
    private $logLevel;
    /**
     * @var string
     */
    private $logMessage;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'log_message' => self::DEFAULT_LOG_MESSAGE,
            'log_level' => self::DEFAULT_LOG_LEVEL
        ]);

        $resolver->setAllowedTypes('log_message', 'string');
        $resolver->setAllowedTypes('log_level', 'string');
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        $this->logLevel = $options['log_level'];
        $this->logMessage = $options['log_message'];
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(Message $message, Queue $queue, callable $next)
    {
        $this->logger->log($this->logLevel, $this->logMessage, [
            'body' => $message->getBody(),
            'exchange_name' => $message->getExchangeName(),
            'headers' => $message->getHeaders(),
            'routing_key' => $message->getRoutingKey()
        ]);

        $next($message, $queue);
    }
}
