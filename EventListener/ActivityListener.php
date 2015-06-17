<?php
namespace BrauneDigital\ActivityBundle\EventListener;

use BrauneDigital\ActivityBundle\Entity\Stream\Activity;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\DependencyInjection\ContainerAware;

class ActivityListener extends ContainerAware
{

    public function postLoad(Activity $activity, LifecycleEventArgs $args)
    {
        $entityManager = $args->getEntityManager();

        if ($activity instanceof Activity) {
            $id = $activity->getAuditedEntityId();
            $class = $activity->getObservedClass();
            $repo = $entityManager->getRepository($class);
            $auditedObject = null;
            if($repo != null) {
               $auditedObject =  $repo->findOneById($id);
            }
            $activity->auditedObject = $auditedObject;
        }
    }
}