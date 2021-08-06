<?php

namespace Radish\Middleware\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Psr\Log\LoggerInterface;
use Radish\Broker\Message;
use Radish\Broker\Queue;
use Radish\Middleware\MiddlewareInterface;

class ObjectManagerMiddleware implements MiddlewareInterface
{
    /**
     * @var Registry
     */
    protected $managerRegistry;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Registry $managerRegistry
     * @param LoggerInterface $logger
     */
    public function __construct(Registry $managerRegistry, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * @param Message $message
     * @param Queue $queue
     * @param callable $next
     * @return mixed
     */
    public function __invoke(Message $message, Queue $queue, callable $next)
    {
        $return = $next($message, $queue);

        foreach ($this->managerRegistry->getManagers() as $managerName => $manager) {
            if (!$manager->isOpen()) {
                $this->logger->info(
                    sprintf('Resetting closed ObjectManager "%s"', $managerName),
                    [
                        'object_manager_name' => $managerName,
                        'object_manager_class' => get_class($manager),
                        'routing_key' => $message->getRoutingKey(),
                        'queue' => $queue->getName()
                    ]
                );
                $this->managerRegistry->resetManager($managerName);
            } else {
                $manager->clear();
            }
        }

        return $return;
    }
}
