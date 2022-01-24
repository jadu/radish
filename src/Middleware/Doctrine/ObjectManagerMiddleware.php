<?php

namespace Radish\Middleware\Doctrine;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator;
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
        try {
            return $next($message, $queue);
        } finally {
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

                    if ($manager instanceof EntityManagerInterface) {
                        $eventManager = $manager->getEventManager();

                        /*
                         * Any hydrators for incomplete Doctrine Statements (like via PDO),
                         * eg. IterableResults, will keep a reference to the PDOStatement
                         * which has an internal reference to the PDOConnection. The hydrator
                         * is added as a listener to Doctrine's EventManager, which exists
                         * for the lifetime of the consumer process. The connection will be held open
                         * until there are no more references to it (whether direct or via PDOStatement),
                         * thereby keeping the database connection open until the consumer exits,
                         * therefore we need to remove all remaining hydrators from the EventManager.
                         */
                        foreach ($eventManager->getListeners(Events::onClear) as $listener) {
                            if ($listener instanceof AbstractHydrator) {
                                $eventManager->removeEventListener(Events::onClear, $listener);
                            }
                        }
                    }
                }
            }
        }
    }
}
