<?php

namespace Radish\Producer;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class BlackHoleProducerFactoryTest extends MockeryTestCase
{
    public function testCreate(): void
    {
        $factory = new BlackHoleProducerFactory();
        $this->assertInstanceOf(BlackHoleProducer::class, $factory->create('test'));
    }
}
