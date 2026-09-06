<?php

namespace App\Tests\Service;

use App\Doctrine\ProfileAttributeValueSearchSubscriber;
use App\Entity\Attribute;
use App\Entity\ProfileAttributeValue;
use App\Entity\User;
use App\Service\CvSearchIndexer;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;

final class ProfileAttributeValueSearchSubscriberTest extends TestCase
{
    public function testProfileValuePersistenceRefreshesCandidateCvVectors(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeStatement')->with(self::stringContains('UPDATE cv SET search_vector'));
        $indexer = new CvSearchIndexer($connection);
        $subscriber = new ProfileAttributeValueSearchSubscriber($indexer);
        $value = new ProfileAttributeValue(new User(), new Attribute(), 'new value');
        $event = new LifecycleEventArgs($value, $this->createMock(ObjectManager::class));

        $subscriber->postUpdate($event);
    }
}
