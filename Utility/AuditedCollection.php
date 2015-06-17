<?php
namespace BrauneDigital\ActivityBundle\Utility;

use SimpleThings\EntityAudit\Collection\AuditedCollection as StAuditedCollection;
use BrauneDigital\ActivityBundle\Services\AuditReader as BdAuditReader;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class AuditedCollection  extends StAuditedCollection {

    public function __construct(BdAuditReader $auditReader, $class, ClassMetadataInfo $classMeta, array $associationDefinition, array $foreignKeys, $revision)
    {
        $this->auditReader = $auditReader;
        $this->class = $class;
        $this->foreignKeys = $foreignKeys;
        $this->revision = $revision;
        $this->configuration = $auditReader->getConfiguration();
        $this->metadata = $classMeta;
        $this->associationDefinition = $associationDefinition;
    }

}