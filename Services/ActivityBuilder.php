<?php

namespace BrauneDigital\ActivityBundle\Services;

use BrauneDigital\ActivityBundle\Entity\Stream\Activity;
use Doctrine\ORM\Event\LifecycleEventArgs;
use SimpleThings\EntityAudit\AuditException;
use SimpleThings\EntityAudit\Metadata\MetadataFactory;
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

    /*
     * @var MetadataFactory
     */
    protected $metadataFactory;

    protected $useDoctrineSubscriber;

    protected function getAuditReader() {
        if(!$this->auditReader) {
            $this->auditReader = $this->getContainer()->get('simplethings_entityaudit.reader');
        }
        return $this->auditReader;
    }

    protected function getEM() {
        if(!$this->em) {
            $this->em = $this->getContainer()->get('doctrine')->getEntityManager();
        }
        return $this->em;
    }

    protected function getUserManager(){
        if(!$this->userManager) {
            $this->userManager = $this->getContainer()->get('fos_user.user_manager');
        }
        return $this->userManager;
    }

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        $this->auditConfig = $this->getContainer()->get('simplethings_entityaudit.config');

        $this->metadataFactory = $this->auditConfig->createMetadataFactory();

        //load observed classes
        foreach ($this->getContainer()->getParameter('observed_classes') as $observedClass) {
            $this->observedClasses[] = $observedClass['name'];
        }

        $this->useDoctrineSubscriber = $this->getContainer()->getParameter('doctrine_subscribing');
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

        $className = get_class($entity);
        if (!$this->metadataFactory->isAudited($className)) {
            return false;
        }
        return in_array($className, $this->getObservedClasses());
    }

    public function persist($entity)
    {
        if($this->useDoctrineSubscriber && $this->supportsEntity($entity)) {
            $user = $this->getContainer()->get('security.token_storage')->getToken()->getUser();

            $revisions = $this->getAuditReader()->findRevisions(get_class($entity), $entity->getId());

            if (!isset($revisions[0])) {
                throw AuditException::noRevisionFound(get_class($entity), array($entity->getId()), 'any');
            }

            $curRev = $revisions[0];

            //build activity
            $this->buildActivity(get_class($entity), $entity, $user, $curRev);
            $this->getEM()->flush(); //save activity
        }
    }

    public function update($entity)
    {
        if($this->useDoctrineSubscriber && $this->supportsEntity($entity)) {
            $user = $this->getContainer()->get('security.token_storage')->getToken()->getUser();

            $revisions = $this->getAuditReader()->findRevisions(get_class($entity), $entity->getId());

            if (!isset($revisions[0])) {
                throw AuditException::noRevisionFound(get_class($entity), array($entity->getId()), 'any');
            }

            $curRev = $revisions[0];
            $prevRev = null;

            if (isset($revisions[1])) {
                $prevRev = $revisions[1];
            }

            $this->buildActivity(get_class($entity), $entity, $user, $curRev, $prevRev);
            $this->getEM()->flush(); //save activity
        }
    }

    public function buildActivity($className, $object, $user, $currentRevision, $lastRev = null) {
        $activity = new Activity();
        try {
            $ignoreChanges = false;
            if($lastRev == null) {
                $lastRev = $currentRevision;
                $ignoreChanges = true;
            }
            $source = $this->getAuditReader()->find($className, $object->getId(), $currentRevision->getRev());
            $sourceCe = $this->pickChangedEntity($object->getId(), $this->getAuditReader()->findEntitiesChangedAtRevision($currentRevision->getRev()));
            $target = $this->getAuditReader()->find($className, $object->getId(), $lastRev->getRev());
            $targetCe = $this->pickChangedEntity($object->getId(), $this->getAuditReader()->findEntitiesChangedAtRevision($lastRev->getRev()));
            $changedFields = $this->getChangedFields($className, $source, $target);

            $activity->setChangedFields($changedFields);
            $activity->setObservedClass($className);
            if($ignoreChanges || sizeof($changedFields) > 0) {

                if($user != null) {
                    $activity->setUser($user);
                }
                else if (method_exists($target, 'getUsername')) {
                    $user = $this->getUserManager()->findUserByUsername($target->getUsername());
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
                $this->getEM()->persist($activity);
            }
        } catch(AuditException $e) {
            throw new \LogicException("Couldn't compare: ". $currentRevision->getRev()." and ".$lastRev->getRev()." for ".$className." and obj ".$object->getId());
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