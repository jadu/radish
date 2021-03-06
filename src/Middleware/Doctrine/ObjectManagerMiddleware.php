<?php

namespace Radish\Middleware\Doctrine;

use Doctrine\Common\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Radish\Broker\Message;
use Radish\Broker\Queue;
use Radish\Middleware\MiddlewareInterface;

class ObjectManagerMiddleware implements MiddlewareInterface
{
    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param LoggerInterface $logger
     */
    public function __construct(ManagerRegistry $managerRegistry, LoggerInterface $logger)
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
