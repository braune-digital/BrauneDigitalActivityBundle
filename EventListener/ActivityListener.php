<?php
namespace BrauneDigital\ActivityBundle\EventListener;

use BrauneDigital\ActivityBundle\Entity\Stream\Activity;
use Doctrine\ORM\Event\LifecycleEventArgs;
use SimpleThings\EntityAudit\AuditException;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ActivityListener extends ContainerAware
{
    protected $auditReader;

    public function setContainer(ContainerInterface $container = null) {
        $this->container = $container;
        if($this->container) {
            $this->auditReader = $this->container->get('simplethings_entityaudit.reader');
        }
    }
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

            $activity->baseAudit = null;
            $activity->changedAudit = null;

            //add the versions to the activity to the revision
            if($this->auditReader) {
                try {
                    $activity->changedAudit = $this->auditReader->find(
                        $class.'Audit',
                        $auditedObject->getId(),
                        $activity->getChangeRevisionId()
                    );

                    $activity->baseAudit = $this->auditReader->find(
                        $class.'Audit',
                        $auditedObject->getId(),
                        $activity->getBaseRevisionId()
                    );

                } catch(AuditException $e) {
                    $activity->baseAudit = null;
                    $activity->changedAudit = null;
                }
            }
        }
    }
}