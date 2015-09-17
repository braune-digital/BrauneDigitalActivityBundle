<?php

namespace BrauneDigital\ActivityBundle\Services;

use BrauneDigital\ActivityBundle\Entity\Stream\Activity;
use Doctrine\ORM\Event\LifecycleEventArgs;
use SimpleThings\EntityAudit\AuditException;
use SimpleThings\EntityAudit\Metadata\MetadataFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;

class ActivityBuilder {

    protected $container;

    protected $observedClasses;

    /*
     * @var SimpleThings\EntityAudit\AuditReader
     */
    protected $auditReader;


    protected $auditManager;
    /**
     * @var EntityMananger
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


    protected $entitiesToUpdate = array();

    protected $user;

    protected $userNotFound = false;

    protected $isFlushing = false;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        //load observed classes
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

    protected function getAuditReader() {
        if(!$this->auditReader) {
            $this->auditReader = $this->getContainer()->get('bd_activity.entityaudit.reader');
        }
        return $this->auditReader;
    }

    protected function getAuditManager() {

        if(!$this->auditManager) {
            $this->auditManager = $this->getContainer()->get('bd_activity.entityaudit.manager');
        }
        return $this->auditManager;
    }

    protected function getMetadataFactory() {
        if(!$this->metadataFactory) {
            $this->metadataFactory = $this->getAuditManager()->getMetadataFactory();
        }
        return $this->metadataFactory;
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

    protected function getUser() {

        if($this->userNotFound) {
            return null;
        }
        else if(!$this->user) {

            $token = $this->getContainer()->get('security.token_storage')->getToken();
            $this->user = null;

            if($token) {
                $this->user = $token->getUser();
            }

            if(!$this->user) {
                $this->userNotFound = true;
            }
        }

        return $this->user;
    }

    public function supportsEntity($entity) {

        if(!is_string($entity)) {
            $className = get_class($entity);
        }
        else {
            $className = $entity;
        }

        if (!$this->getMetadataFactory()->isAudited($className)) {
            return false;
        }
        return in_array($className, $this->getObservedClasses());
    }

    public function persistEntity($entity) {
        $this->updateEntity($entity);
    }

    public function removeEntity($entity) {
        $this->updateEntity($entity);
    }

    public function updateEntity($entity)
    {
        if($this->supportsEntity($entity)) {
            array_push($this->entitiesToUpdate, array('class' => get_class($entity), 'id' => $entity->getId()));
        }
    }

    protected function updateEntityAsClass($class, $id) {

        if($this->supportsEntity($class)) {

            $this->getUser();

            $revisions = $this->getAuditReader()->findRevisions($class, $id);

            if (!isset($revisions[0])) {
                throw AuditException::noRevisionFound($class, array($id), 'any');
            }

            $curRev = $revisions[0];
            $prevRev = null;

            if (isset($revisions[1])) {
                $prevRev = $revisions[1];
            }

            $this->buildActivityId($class, $id, $this->getUser(), $curRev, $prevRev);
        }
    }

    public function updateEntities() {
        foreach($this->entitiesToUpdate as $e) {
            $this->updateEntityAsClass($e['class'], $e['id']);
        }
        if(count($this->entitiesToUpdate) > 0) {
            $this->getEM()->flush();
        }
    }

    public function onKernelTerminate(PostResponseEvent $event) {
        $this->updateEntities();
    }

    public function buildActivity($className, $object, $user, $currentRevision, $lastRev = null) {
        $this->buildActivityId($className, $object->getId(), $user, $currentRevision, $lastRev);
    }
    public function buildActivityId($className, $id, $user, $currentRevision, $lastRev = null) {

        if(!$id) {
            throw new \LogicException("Can't build an activity from a NULL id");
        }
        $activity = new Activity();
        try {
            $ignoreChanges = false;
            if($lastRev == null) {
                $lastRev = $currentRevision;
                $ignoreChanges = true;
            }
            $source = $this->getAuditReader()->find($className, $id, $currentRevision->getRev());
            $sourceCe = $this->pickChangedEntity($id, $this->getAuditReader()->findEntitiesChangedAtRevision($currentRevision->getRev()));
            $target = $this->getAuditReader()->find($className, $id, $lastRev->getRev());
            $targetCe = $this->pickChangedEntity($id, $this->getAuditReader()->findEntitiesChangedAtRevision($lastRev->getRev()));
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
                $activity->setAuditedEntityId($id);
                $activity->setBaseRevisionId($currentRevision->getRev());
                $activity->setBaseRevisionRevType($sourceCe->getRevisionType());
                $activity->setChangeRevisionId($lastRev->getRev());
                $activity->setChangeRevisionRevType($targetCe->getRevisionType());
                $activity->setChangedDate($lastRev->getTimestamp());
                $this->getEM()->persist($activity);
            }
        } catch(AuditException $e) {
            throw new \LogicException("Couldn't compare: ". $currentRevision->getRev()." and ".$lastRev->getRev()." for ".$className." and obj ".$id);
        }
    }

    public function postLoadActivity(Activity $activity) {

        //add fields dynamically
        $id = $activity->getAuditedEntityId();
        $class = $activity->getObservedClass();
        $repo = $this->getEM()->getRepository($class);
        $auditedObject = null;
        if($repo != null) {
            $auditedObject =  $repo->findOneById($id);
        }
        $activity->auditedObject = $auditedObject;

        //add the revision versions to the activity
        try {
            $activity->changedAudit = $this->getAuditReader()->find(
                $class,
                $activity->getAuditedEntityId(),
                $activity->getChangeRevisionId()
            );

            $activity->baseAudit = $this->getAuditReader()->find(
                $class,
                $activity->getAuditedEntityId(),
                $activity->getBaseRevisionId()
            );

        } catch(\Exception $e) {
            $activity->changedAudit = null;
            $activity->baseAudit = null;
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