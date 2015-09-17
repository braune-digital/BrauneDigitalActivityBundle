<?php

namespace BrauneDigital\ActivityBundle\Services;

use Doctrine\ORM\EntityManager;
use SimpleThings\EntityAudit\AuditManager as BaseAuditManager;

/**
 * Audit Manager grants access to metadata and configuration
 * and has a factory method for audit queries.
 */
class AuditManager extends BaseAuditManager
{
    public function createAuditReader(EntityManager $em)
    {
        return new AuditReader($em, $this->getConfiguration(), $this->getMetadataFactory());
    }
}