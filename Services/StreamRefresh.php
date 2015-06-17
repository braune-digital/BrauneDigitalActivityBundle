<?php

namespace BrauneDigital\ActivityBundle\Services;

use BrauneDigital\ActivityBundle\Entity\Stream\Activity;
use Symfony\Component\Debug\Exception\UndefinedFunctionException;
use Symfony\Component\Debug\Exception\UndefinedMethodException;
use Symfony\Component\Yaml\Yaml;
use Doctrine\ORM\EntityManager;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use SimpleThings\EntityAudit\Revision;
use SimpleThings\EntityAudit\Exception\NoRevisionFoundException;
use Symfony\Component\Console\Output\OutputInterface;

class StreamRefresh {
    const RELATIVE_CONFIG_PATH = '/../Resources/config/config.yml';

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var ContainerInterface
     */
    private $container;

    /*
     * @var BrauneDigital\ActivityBundle\Entity\Stream\ActivityRepository
     */
    private $activityRepository;

    /*
     * @var BrauneDigital\ActivityBundle\Entity\Stream\StreamRepository
     */
    private $streamRepository;

    /*
     * @var FOS\UserBundle\Entity\UserManager
     */
    private $userManager;


    /*
     * @var SimpleThings\EntityAudit\AuditReader
     */
    private $auditReader;

    /*
     * @var SimpleThings\EntityAudit\AuditConfiguration
     */
    private $auditConfig;

    /*
     * @var Metadata\MetadataFactory
     */
    private $mdFactory;

    /*
     * @var \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    private $platform;

    /*
     * @var OutputInterface
     */
    private $output;




    public function __construct(EntityManager $em, ContainerInterface $container)
    {
        $this->em = $em;
        $this->container = $container;
        $this->activityRepository = $em->getRepository('BrauneDigital\ActivityBundle\Entity\Stream\Activity');
        $this->streamRepository = $em->getRepository('BrauneDigital\ActivityBundle\Entity\Stream\Stream');
        $this->userManager = $this->getContainer()->get('fos_user.user_manager');
        $this->auditReader = $container->get("bd_activity.entityaudit.reader");
        $this->auditConfig = $container->get("simplethings_entityaudit.config");
        $this->mdFactory = $this->auditConfig->createMetadataFactory();
        $this->platform = $this->em->getConnection()->getDatabasePlatform();
    }

    public function setContainer (ContainerInterface $container) {
        $this->container = $container;
    }

    public function getContainer () {
        return $this->container;
    }

    /**
     * Limit the number of new objects added to the stream
     *
     * @var integer
     */
    private $buildLimit = -1;

    /*
     * Refresh the set of connected activities
     */
    public function refresh() {
        $fullBackendStream = $this->streamRepository->createOrGetFullBackendStream();

        $observedClasses = $this->getObservedClasses();

        foreach($observedClasses as $observedClass) {
            if($this->refreshRequired($observedClass)) {
                $this->refreshStreamForClass($observedClass);
            }
        }
    }

    private function refreshStreamForClass($className) {
        $startAtRevision = $this->getPickupRevision($className);

        $batchSize = 20;
        $i = 0;
        $q = $this->em->createQuery('select cl from :className cl')->setParameter(':className', $className);
        $q = $this->em
			->createQueryBuilder()
			->select('cl')
			->from($className, 'cl')
			->getQuery();
        $iterableResult = $q->iterate();
        while (($row = $iterableResult->next()) !== false && ($this->buildLimit > 0 || $this->buildLimit == -1)) {
            $this->refreshStreamForEntity($className, $row[0], $startAtRevision);
            if (($i % $batchSize) === 0) {
                $this->em->flush(); // Executes all deletions.
                $this->em->clear(); // Detaches all objects from Doctrine!
            }
            ++$i;
        }
        $this->em->flush(); // Executes all deletions.
        $this->em->clear();
    }

    private function refreshStreamForEntity($className, $object, $startAtRev) {

        $revisionsClass = $className;

        if($startAtRev) {
            $revisions = $this->auditReader->findRevisionsFrom($revisionsClass, $object->getId(), $startAtRev);
        } else {
            $revisions = $this->auditReader->findRevisions($revisionsClass, $object->getId());
        }

        $lastRev = null;
        $checkCreateRevision = null;
        foreach($revisions as $currentRevision) {
            if($this->buildLimit > 0 || $this->buildLimit == -1) {
                if(($lastRev == null && sizeof($revisions) > 1)) {
                    $lastRev = $currentRevision;
                } else {
                    if(sizeof($revisions) > 1) {
                        $this->addActivity($className, $object, $lastRev, $currentRevision);
                        $lastRev = $currentRevision;
                    }
                    $checkCreateRevision = $currentRevision;
                }
            }
        }
        if($checkCreateRevision && ! $this->activityRepository->getCreateRevision($className, $object)) {
            $this->addActivityIgnoreChanges($className, $object, $checkCreateRevision , $checkCreateRevision);
        }
    }

    private function addActivity($className, $object,$lastRev, $currentRevision) {
        $activity = new Activity();
        try {
            $source = $this->auditReader->find($className, $object->getId(), $currentRevision->getRev());
            $sourceCe = $this->pickChangedEntity($object->getId(), $this->auditReader->findEntitiesChangedAtRevision($currentRevision->getRev()));
            $target = $this->auditReader->find($className, $object->getId(), $lastRev->getRev());
            $targetCe = $this->pickChangedEntity($object->getId(), $this->auditReader->findEntitiesChangedAtRevision($lastRev->getRev()));
            $changedFields = $this->getChangedFields($className, $source, $target);

            $activity->setChangedFields($changedFields);
            $activity->setObservedClass($className);
            if(sizeof($changedFields) > 0) {
                if (method_exists($target, 'getUsername')) {
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
                if($this->buildLimit != -1 && $this->buildLimit > 0) {
                    $this->buildLimit = --$this->buildLimit;
                }
            }
        } catch(NoRevisionFoundException $e) {
            if($this->output) {
                $this->output->writeln("Couldn't compare: ". $currentRevision->getRev()." and ".$lastRev->getRev()." for ".$className." and obj ".$object->getId());
            }
        }
    }

    private function addActivityIgnoreChanges($className, $object,$lastRev, $currentRevision) {
        $activity = new Activity();
        try {
            $source = $this->auditReader->find($className, $object->getId(), $currentRevision->getRev());
            $sourceCe = $this->pickChangedEntity($object->getId(), $this->auditReader->findEntitiesChangedAtRevision($currentRevision->getRev()));
            $target = $this->auditReader->find($className, $object->getId(), $lastRev->getRev());
            $targetCe = $this->pickChangedEntity($object->getId(), $this->auditReader->findEntitiesChangedAtRevision($lastRev->getRev()));
            $changedFields = $this->getChangedFields($className, $source, $target);

            $activity->setChangedFields($changedFields);
            $activity->setObservedClass($className);
            if (method_exists($target, 'getUsername')) {
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
            if($this->buildLimit != -1 && $this->buildLimit > 0) {
                $this->buildLimit = --$this->buildLimit;
            }
        } catch(NoRevisionFoundException $e) {
            if($this->output) {
                $this->output->writeln("Couldn't compare: ". $currentRevision->getRev()." and ".$lastRev->getRev()." for ".$className." and obj ".$object->getId());
            }
        }
    }

    private function getChangedFields($className, $source, $target) {
        $observedFields = $this->getFieldsForClass($className);
        $changedFields = array();

        foreach($observedFields as $observedField) {
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

    private function pickChangedEntity($id, $changedEntities) {
        foreach($changedEntities as $entity) {
            if($entity->getEntity()->getId() == $id) {
                return $entity;
            }
        }
    }

    private function revisionsSorted($revisions) {
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
     *  Returns true if a activity refresh is required for the specified class
     *
     * @return boolean
     */
    private function refreshRequired($className) {
        $refreshRequired = false;
        $latestActivity = $this->activityRepository->latestForClass($className);
        if(! empty($latestActivity)) {
            $latestRevWithActivity = $latestActivity->getChangeRevisionId();
            $revisions = $this->auditReader->revisionsLargerThan($className, $latestRevWithActivity);
            if(sizeof($revisions) > 0) {
                $refreshRequired = true;
            }
        } else {
            $refreshRequired = true;
        }

        return $refreshRequired;
    }

    /*
     * @return Revision
     */

    private function getPickupRevision($className) {
        $latestActivity = $this->activityRepository->latestForClass($className);

        if(! empty($latestActivity)) {
            return $latestActivity->getChangeRevisionId();
        }

        return null;
    }

    /**
     * Return array of observed classnames
     *
     * @return array
     */
    private function getObservedClasses() {
        $yaml = Yaml::parse($this->getConfigPath());
        $classes = array();
        foreach($yaml['bd_activity']['observed_classes'] as $observedClass) {
            $classes[] = $observedClass['name'];
        }
        return $classes;
    }

    /**
     * Return array of observed classnames
     *
     * @return array
     */
    private function getFieldsForClass($className) {
        $yaml = Yaml::parse($this->getConfigPath());
        foreach($yaml['bd_activity']['observed_classes'] as $observedClass) {
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
    private function getConfigPath() {
        return __DIR__ . StreamRefresh::RELATIVE_CONFIG_PATH;
    }

    /**
     * @return integer
     */
    public function getBuildLimit()
    {
        return $this->buildLimit;
    }

    /**
     * @param integer $buildLimit
     */
    public function setBuildLimit($buildLimit)
    {
        $this->buildLimit = $buildLimit;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output) {
        $this->output = $output;
    }
}