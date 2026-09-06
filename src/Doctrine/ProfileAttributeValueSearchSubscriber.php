<?php

namespace App\Doctrine;

use App\Entity\ProfileAttributeValue;
use App\Service\CvSearchIndexer;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

final class ProfileAttributeValueSearchSubscriber implements EventSubscriber
{
    public function __construct(private readonly CvSearchIndexer $indexer)
    {
    }

    public function getSubscribedEvents(): array
    {
        return [Events::postPersist, Events::postUpdate];
    }

    public function postPersist(LifecycleEventArgs $event): void
    {
        $this->refresh($event);
    }

    public function postUpdate(LifecycleEventArgs $event): void
    {
        $this->refresh($event);
    }

    private function refresh(LifecycleEventArgs $event): void
    {
        $value = $event->getObject();
        if ($value instanceof ProfileAttributeValue) {
            $this->indexer->refreshCandidate($value->getUser());
        }
    }
}