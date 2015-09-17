<?php
namespace BrauneDigital\ActivityBundle\EventListener;

use BrauneDigital\ActivityBundle\Entity\Stream\Activity;
use BrauneDigital\ActivityBundle\Services\ActivityBuilder;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

class DoctrineSubscriber implements EventSubscriber
{
    public function __construct(ActivityBuilder $activityBuilder, $enabled) {
        $this->activityBuilder = $activityBuilder;
        $this->enabled = $enabled;
    }
    public function getSubscribedEvents()
    {
        if(!$this->enabled) {
            return array(Events::postLoad);
        }
        else {
            return array(Events::postUpdate, Events::postPersist, Events::postLoad, Events::preRemove);
        }
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $this->activityBuilder->updateEntity($args->getEntity());
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $this->activityBuilder->persistEntity($args->getEntity());
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $this->activityBuilder->removeEntity($args->getEntity());
    }

    public function postLoad(LifecycleEventArgs $args)
    {
        $activity = $args->getEntity();
        if ($activity instanceof Activity) {
            $this->activityBuilder->postLoadActivity($args->getEntity());
        }
    }
}