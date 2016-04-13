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
     * @var SimpleThings\EntityAudit\AuditReader
     */
    protected $auditReader;

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

        $this->auditReader = $container->get('bd_activity.entityaudit.reader');

        $this->platform = $this->em->getConnection()->getDatabasePlatform();

        $this->activityBuilder = $this->getContainer()->get('bd_activity.activity_builder');
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

        $observedClasses = $this->activityBuilder->getObservedClasses();

        foreach($observedClasses as $class => $observedClass) {
            if($this->refreshRequired($class)) {
                $this->refreshStreamForClass($class);
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
                        $this->activityBuilder->buildActivity($className, $object, null, $currentRevision, $lastRev);
                        --$this->buildLimit;
                        $lastRev = $currentRevision;
                    }
                    $checkCreateRevision = $currentRevision;
                }
            }
        }
        if($checkCreateRevision && ! $this->activityRepository->getCreateRevision($className, $object)) {
            $this->activityBuilder->buildActivity($className, $object, null, $checkCreateRevision);
            --$this->buildLimit;
        }
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