<?php

namespace BrauneDigital\ActivityBundle\Entity\Stream;

use Doctrine\ORM\Mapping as ORM;

/**
 * Stream
 *
 * @ORM\Table(name="bd_activity_stream")
 * @ORM\Entity(repositoryClass="BrauneDigital\ActivityBundle\Entity\Stream\StreamRepository")
 */
class Stream
{

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }
}

