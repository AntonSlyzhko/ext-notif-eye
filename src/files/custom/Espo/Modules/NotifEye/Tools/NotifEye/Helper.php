<?php

namespace Espo\Modules\NotifEye\Tools\NotifEye;

use Espo\Core\Acl;
use Espo\Core\Utils\Metadata;
use Espo\Core\Acl\Table;

class Helper
{
    public function __construct(
        private Metadata $metadata,
        private Acl $acl
    ) {}

    public function isToUseNotifEyeFunctionality(string $entityType): bool
    {
        if (!$this->isStreamEnabled($entityType)) {
            return false;
        }

        if (!$this->isNotifEyeEnabled($entityType)) {
            return false;
        }

        return $this->isStreamAllowed($entityType);
    }

    private function isNotifEyeEnabled(string $entityType): bool
    {
        return (bool) $this->metadata->get("scopes.$entityType.notifEye", false);
    }

    private function isStreamEnabled(string $entityType): bool
    {
        return (bool) $this->metadata->get("scopes.$entityType.stream", false);
    }

    private function isStreamAllowed(string $entityType): bool
    {
        return $this->acl->getLevel($entityType, Table::ACTION_STREAM) !== Table::LEVEL_NO;
    }
}
