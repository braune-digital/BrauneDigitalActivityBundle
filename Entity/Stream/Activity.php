<?php

namespace BrauneDigital\ActivityBundle\Entity\Stream;

use Doctrine\ORM\Mapping as ORM;
use Application\Sonata\UserBundle\Entity\User as User;

/**
 * Activity
 *
 * @ORM\Table(name="bd_activity_activity")
 * @ORM\Entity(repositoryClass="BrauneDigital\ActivityBundle\Entity\Stream\ActivityRepository")
 * @ORM\EntityListeners({"BrauneDigital\ActivityBundle\EventListener\ActivityListener"})
 */
class Activity
{
    const REVIEW_STATE_UNREVIEWED = 'unreviewed';
    const REVIEW_STATE_APPROVED = 'approved';
    const REVIEW_STATE_REJECTED = 'rejected';

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     */
    private $reviewState = Activity::REVIEW_STATE_UNREVIEWED;

    /**
     * @var array
     *
     * @ORM\Column(type="array")
     */
    private $changedFields;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     */
    private $observedClass;

    /**
     * @var Application\Sonata\UserBundle\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="BrauneDigital\ActivityBundle\Model\UserInterface", inversedBy="activities")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     **/
    private $user;

    /**
     * @var integer
     *
     * @ORM\Column(type="integer")
     */
    private $auditedEntityId;

    /**
     * @var integer
     *
     * @ORM\Column(type="integer")
     */
    private $baseRevisionId;

    /**
     * @var integer
     *
     * @ORM\Column(type="integer")
     */
    private $changeRevisionId;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=4)
     */
    private $baseRevisionRevType;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=4)
     */
    private $changeRevisionRevType;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime")
     */
    private $changedDate;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getAuditedEntityId()
    {
        return $this->auditedEntityId;
    }

    /**
     * @param int $auditedEntityId
     */
    public function setAuditedEntityId($auditedEntityId)
    {
        $this->auditedEntityId = $auditedEntityId;
    }

    /**
     * @return int
     */
    public function getBaseRevisionId()
    {
        return $this->baseRevisionId;
    }

    /**
     * @param int $baseRevisionId
     */
    public function setBaseRevisionId($baseRevisionId)
    {
        $this->baseRevisionId = $baseRevisionId;
    }

    /**
     * @return string
     */
    public function getBaseRevisionRevType()
    {
        return $this->baseRevisionRevType;
    }

    /**
     * @param string $baseRevisionRevType
     */
    public function setBaseRevisionRevType($baseRevisionRevType)
    {
        $this->baseRevisionRevType = $baseRevisionRevType;
    }

    /**
     * @return string
     */
    public function getChangeRevisionRevType()
    {
        return $this->changeRevisionRevType;
    }

    /**
     * @param string $changeRevisionRevType
     */
    public function setChangeRevisionRevType($changeRevisionRevType)
    {
        $this->changeRevisionRevType = $changeRevisionRevType;
    }

    /**
     * @return string
     */
    public function getReviewState()
    {
        return $this->reviewState;
    }

    /**
     * @param string $reviewState
     */
    public function setReviewState($reviewState)
    {
        $this->reviewState = $reviewState;
    }

    /**
     * @return array
     */
    public function getChangedFields()
    {
        return $this->changedFields;
    }

    /**
     * @param array $changedFields
     */
    public function setChangedFields($changedFields)
    {
        $this->changedFields = $changedFields;
    }

    /**
     * @return string
     */
    public function getObservedClass()
    {
        return $this->observedClass;
    }

    /**
     * @param string $observedClass
     */
    public function setObservedClass($observedClass)
    {
        $this->observedClass = $observedClass;
    }

    /**
     * @return int
     */
    public function getChangeRevisionId()
    {
        return $this->changeRevisionId;
    }

    /**
     * @param int $changeRevisionId
     */
    public function setChangeRevisionId($changeRevisionId)
    {
        $this->changeRevisionId = $changeRevisionId;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param User
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return \DateTime
     */
    public function getChangedDate()
    {
        return $this->changedDate;
    }

    /**
     * @param \DateTime $changedDate
     */
    public function setChangedDate($changedDate)
    {
        $this->changedDate = $changedDate;
    }

}

