<?php

namespace BrauneDigital\ActivityBundle\Services;

use BrauneDigital\ActivityBundle\Entity\Stream\Activity;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ActivityBuilder {

    protected $container;

    protected $observedClasses;


    /*
     * @var SimpleThings\EntityAudit\AuditReader
     */
    protected $auditReader;

    /**
     * @var EntityManager
     */
    protected $em;

    /*
     * @var FOS\UserBundle\Entity\UserManager
     */
    private $userManager;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        $this->auditReader = $container->get("bd_activity.entityaudit.reader");

        $this->em = $this->getContainer()->get('doctrine')->getEntityManager();

        $this->userManager = $this->getContainer()->get('fos_user.user_manager');

        foreach ($this->getContainer()->getParameter('observed_classes') as $observedClass) {
            $this->observedClasses[] = $observedClass['name'];
        }
    }

    /**
     * @return ContainerInterface
     */
    protected function getContainer()
    {
        return $this->container;
    }


    public function getObservedClasses() {
        return $this->observedClasses;
    }

    public function supportsEntity($entity) {
        return in_array(get_class($entity), $this->getObservedClasses());
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $entityManager = $args->getEntityManager();

        if($this->supportsEntity($entityManager)) {

        }
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $entityManager = $args->getEntityManager();

        $user = $this->getContainer()->get('securiy.token_storage')->getToken()->getUser();

        $curRev = null;

        if($this->supportsEntity($entity)) {
            $this->buildActivity(get_class($entity), $entity, $user, $curRev);
        }
    }

    protected function buildActivity($className, $object, $user, $currentRevision, $lastRev = null) {
        $activity = new Activity();
        try {
            $ignoreChanges = false;
            if($lastRev == null) {
                $lastRev = $currentRevision;
                $ignoreChanges = true;
            }
            $source = $this->auditReader->find($className, $object->getId(), $currentRevision->getRev());
            $sourceCe = $this->pickChangedEntity($object->getId(), $this->auditReader->findEntitiesChangedAtRevision($currentRevision->getRev()));
            $target = $this->auditReader->find($className, $object->getId(), $lastRev->getRev());
            $targetCe = $this->pickChangedEntity($object->getId(), $this->auditReader->findEntitiesChangedAtRevision($lastRev->getRev()));
            $changedFields = $this->getChangedFields($className, $source, $target);

            $activity->setChangedFields($changedFields);
            $activity->setObservedClass($className);
            if($ignoreChanges || sizeof($changedFields) > 0) {
                if ($user == null && method_exists($target, 'getUsername')) {
                    $user = $this->userManager->findUserByUsername($target->getUsername());
                    if(!empty($user)) {
                        $activity->setUser($user);
                    }
                }
                $activity->setAuditedEntityId($object->getId());
                $activity->setBaseRevisionId($currentRevision->getRev());
                $activity->setBaseRevisionRevType($sourceCe->getRevisionType());
                $activity->setChangeRevisionId($lastRev->getRev());
                $activity->setChangeRevisionRevType($targetCe->getRevisionType());
                $activity->setChangedDate($lastRev->getTimestamp());
                $this->em->persist($activity);
            }
        } catch(NoRevisionFoundException $e) {
            if($this->output) {
                $this->output->writeln("Couldn't compare: ". $currentRevision->getRev()." and ".$lastRev->getRev()." for ".$className." and obj ".$object->getId());
            }
        }
    }

    protected function getChangedFields($className, $source, $target) {
        $observedFields = $this->getFieldsForClass($className);
        $changedFields = array();

        foreach($observedFields as $observedField) {
            $observedField = $observedField['fieldName'];
            $getter = 'get'.ucwords($observedField);

            $sourceValue = call_user_func(array($source, $getter));
            $targetValue = call_user_func(array($target, $getter));
            if(is_object($sourceValue) || is_object($targetValue)) {
                if(!is_object($sourceValue) || !is_object($targetValue)) {
                    $changedFields[] = $observedField;
                } else {
                    if (method_exists($sourceValue, 'getId') && method_exists($targetValue, 'getId')) {
                        if($sourceValue->getId() != $targetValue->getId()) {
                            $changedFields[] = $observedField;
                        }

                    }
                }
            } else {
                if($sourceValue != $targetValue) {
                    $changedFields[] = $observedField;
                }
            }
        }

        return $changedFields;
    }

    protected function pickChangedEntity($id, $changedEntities) {
        foreach($changedEntities as $entity) {
            if($entity->getEntity()->getId() == $id) {
                return $entity;
            }
        }
    }

    protected function revisionsSorted($revisions) {
        $lastRev = null;
        foreach($revisions as $currentRevision) {
            if($lastRev == null) {
                $lastRev = $currentRevision;
            } else {
                if($lastRev->getRev() <= $currentRevision->getRev()) {
                    return false;
                } else {
                    $lastRev = $currentRevision;
                }
            }
        }
        return true;
    }

    /**
     * Return array of observed classnames
     *
     * @return array
     */
    protected function getFieldsForClass($className) {
        foreach($this->getContainer()->getParameter('observed_classes') as $observedClass) {
            if($observedClass['name'] == $className) {
                return $observedClass['fields'];
            }
        }
    }

    /**
     * Returns the full config path where the observed classes are configured
     *
     * @return string
     */
    protected function getConfigPath() {
        return __DIR__ . StreamRefresh::RELATIVE_CONFIG_PATH;
    }
}