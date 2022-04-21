<?php

namespace Radish\Middleware\Doctrine;

use Doctrine\Common\EventManager;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator;
use Doctrine\Persistence\ObjectManager;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Radish\Broker\Message;
use Radish\Broker\Queue;

/**
 * Class ObjectManagerMiddlewareTest.
 *
 * @author Jadu Ltd.
 */
class ObjectManagerMiddlewareTest extends \PHPUnit_Framework_TestCase
{
    public $callable;
    /**
     * @var MockInterface|LoggerInterface
     */
    public $logger;
    /**
     * @var MockInterface|Registry
     */
    public $managerRegistry;
    /**
     * @var MockInterface|Message
     */
    public $message;
    /**
     * @var ObjectManagerMiddleware
     */
    public $middleware;
    /**
     * @var MockInterface|Queue
     */
    public $queue;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        $this->logger = Mockery::mock(LoggerInterface::class);

        $this->message = Mockery::mock(Message::class);

        $this->managerRegistry = Mockery::mock(Registry::class);

        $this->queue = Mockery::mock(Queue::class);

        $this->callable = function () {
            return 'ok';
        };


        $this->middleware = new ObjectManagerMiddleware($this->managerRegistry, $this->logger);
    }

    public function testMiddlewareWithANonOpenManager(): void
    {
        $manager = Mockery::mock(ObjectManager::class, [
            'isOpen' => false
        ]);
        $this->message->shouldReceive('getRoutingKey')
            ->once()
            ->andReturn('message-key');

        $this->queue->shouldReceive('getName')
            ->once()
            ->andReturn('the-queue');

        $this->logger->shouldReceive('info')
            ->once()
            ->withArgs([
                'Resetting closed ObjectManager "manager-name"',
                [
                    'object_manager_name' => 'manager-name',
                    'object_manager_class' => get_class($manager),
                    'routing_key' => 'message-key',
                    'queue' => 'the-queue'
                ]
            ]);

        $this->managerRegistry->shouldReceive('getManagers')
            ->once()
            ->andReturn(['manager-name' => $manager]);
        $this->managerRegistry->shouldReceive('resetManager')
            ->once()
            ->with('manager-name');

        $middleware = $this->middleware;
        static::assertEquals('ok', $middleware($this->message, $this->queue, $this->callable));
    }

    public function testMiddlewareWithAnOpenEntityManagerThatIsAnAbstractHydrator(): void
    {
        $listener = Mockery::mock(AbstractHydrator::class);

        $eventManager = Mockery::mock(EventManager::class);
        $eventManager->shouldReceive('getListeners')
            ->once()
            ->with(Events::onClear)
            ->andReturn([$listener]);
        $eventManager->shouldReceive('removeEventListener')
            ->once()
            ->withArgs([
                Events::onClear,
                $listener
            ]);

        $manager = Mockery::mock(EntityManagerInterface::class, [
            'isOpen' => true
        ]);

        $manager->shouldReceive('clear')
            ->once();

        $manager->shouldReceive('getEventManager')
            ->once()
            ->andReturn($eventManager);

        $this->managerRegistry->shouldReceive('getManagers')
            ->once()
            ->andReturn(['manager-name' => $manager]);

        $middleware = $this->middleware;
        static::assertEquals('ok', $middleware($this->message, $this->queue, $this->callable));
    }

    public function testMiddlewareWithAnOpenEntityManagerThatIsNotAbstractHydrator(): void
    {
        // I just put a random class that is not an Abstract Hydrator
        $listener = Mockery::mock(AbstractAsset::class);

        $eventManager = Mockery::mock(EventManager::class);
        $eventManager->shouldReceive('getListeners')
            ->once()
            ->with(Events::onClear)
            ->andReturn([$listener]);
        $eventManager->shouldNotReceive('removeEventListener');

        $manager = Mockery::mock(EntityManagerInterface::class, [
            'isOpen' => true
        ]);

        $manager->shouldReceive('clear')
            ->once();

        $manager->shouldReceive('getEventManager')
            ->once()
            ->andReturn($eventManager);

        $this->managerRegistry->shouldReceive('getManagers')
            ->once()
            ->andReturn(['manager-name' => $manager]);

        $middleware = $this->middleware;
        static::assertEquals('ok', $middleware($this->message, $this->queue, $this->callable));
    }

    public function testMiddlewareWithAnOpenManager(): void
    {
        $manager = Mockery::mock(ObjectManager::class, [
            'isOpen' => true
        ]);

        $manager->shouldReceive('clear')
            ->once();

        $this->managerRegistry->shouldReceive('getManagers')
            ->once()
            ->andReturn(['manager-name' => $manager]);

        $middleware = $this->middleware;
        static::assertEquals('ok', $middleware($this->message, $this->queue, $this->callable));
    }

    /**
     * @dataProvider returnProvider
     */
    public function testMiddlewareWithoutManagers($return): void
    {
        $this->managerRegistry->shouldReceive('getManagers')
            ->once()
            ->andReturn([]);

        $next = function () use ($return) {
            return $return;
        };

        $middleware = $this->middleware;
        static::assertEquals($return, $middleware($this->message, $this->queue, $next));
    }

    /**
     * @return array
     */
    private function returnProvider(): array
    {
        return [
            [true],
            [false]
        ];
    }
}
