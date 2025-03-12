<?php

namespace Espo\Modules\NotifEye\Tools\EntityManager\Hooks;

use Espo\Core\ORM\Type\FieldType;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Metadata;
use Espo\Modules\NotifEye\Classes\FieldProcessing\HasUnreadNotifications;
use Espo\Modules\NotifEye\Classes\RecordHooks\ReadUnreadNotifications;
use Espo\ORM\Defs\Params\FieldParam;
use Espo\Tools\EntityManager\Hook\UpdateHook;
use Espo\Tools\EntityManager\Params;

/**
 * @noinspection PhpUnused
 */
class NotifEyeUpdateHook implements UpdateHook
{
    private const PARAM = 'notifEye';
    private const FIELD = 'hasUnreadNotifications';
    private const VIEW_SETUP_HANDLER = 'notif-eye:view-setup-handlers/row-highlighter';
    private const APPEND = '__APPEND__';

    public function __construct(
        private Metadata $metadata,
        private Log $log
    ) {}

    public function process(Params $params, Params $previousParams): void
    {
        if ($params->get(self::PARAM) && !$previousParams->get(self::PARAM)) {
            $this->add($params->getName());
        } else if (!$params->get(self::PARAM) && $previousParams->get(self::PARAM)) {
            $this->remove($params->getName());
        }
    }

    private function add(string $entityType): void
    {
        if ($this->metadata->get("entityDefs.$entityType.fields." . self::FIELD . ".isCustom")) {
            $this->log->warning("Cannot enable NotifEye for $entityType as the field already exists.");
            return;
        }

        $this->metadata->set('entityDefs', $entityType, [
            'fields' => [
                self::FIELD => [
                    FieldParam::TYPE => FieldType::BOOL,
                    FieldParam::NOT_STORABLE => true,
                    'readOnly' => true,
                    'utility' => true,
                    'layoutAvailabilityList' => [],
                    'customizationDisabled' => true
                ]
            ]
        ]);
        $this->metadata->save();

        $clientDefs = $this->metadata->getCustom('clientDefs', $entityType) ?? (object) [];
        $clientDefs->viewSetupHandlers ??= (object) [];
        $clientDefs->viewSetupHandlers->{'record/list'} ??= [];
        if (!in_array(self::VIEW_SETUP_HANDLER, $clientDefs->viewSetupHandlers->{'record/list'})) {
            if (!in_array(self::APPEND, $clientDefs->viewSetupHandlers->{'record/list'})) {
                array_unshift($clientDefs->viewSetupHandlers->{'record/list'}, self::APPEND);
            }
            $clientDefs->viewSetupHandlers->{'record/list'}[] = self::VIEW_SETUP_HANDLER;
            $this->metadata->saveCustom('clientDefs', $entityType, $clientDefs);
        }

        $saveRecordDefs = false;
        $recordDefs = $this->metadata->getCustom('recordDefs', $entityType) ?? (object) [];
        $recordDefs->listLoaderClassNameList ??= [];
        if (!in_array(HasUnreadNotifications::class, $recordDefs->listLoaderClassNameList)) {
            if (!in_array(self::APPEND, $recordDefs->listLoaderClassNameList)) {
                array_unshift($recordDefs->listLoaderClassNameList, self::APPEND);
            }
            $recordDefs->listLoaderClassNameList[] = HasUnreadNotifications::class;
            $saveRecordDefs = true;
        }
        $recordDefs->beforeReadHookClassNameList ??= [];
        if (!in_array(ReadUnreadNotifications::class, $recordDefs->beforeReadHookClassNameList)) {
            if (!in_array(self::APPEND, $recordDefs->beforeReadHookClassNameList)) {
                array_unshift($recordDefs->beforeReadHookClassNameList, self::APPEND);
            }
            $recordDefs->beforeReadHookClassNameList[] = ReadUnreadNotifications::class;
            $saveRecordDefs = true;
        }

        if ($saveRecordDefs) {
            $this->metadata->saveCustom('recordDefs', $entityType, $recordDefs);
        }
    }

    private function remove(string $entityType): void
    {
        if (
            $this->metadata->get("entityDefs.$entityType.fields." . self::FIELD . ".isCustom")
        ) {
            return;
        }

        $this->metadata->delete('entityDefs', $entityType, [
            'fields.' . self::FIELD
        ]);
        $this->metadata->save();

        $clientDefs = $this->metadata->getCustom('clientDefs', $entityType) ?? (object) [];
        $clientDefs->viewSetupHandlers ??= (object) [];
        $clientDefs->viewSetupHandlers->{'record/list'} ??= [];
        if (in_array(self::VIEW_SETUP_HANDLER, $clientDefs->viewSetupHandlers->{'record/list'})) {
            $clientDefs->viewSetupHandlers->{'record/list'} = array_values(
                array_filter(
                    $clientDefs->viewSetupHandlers->{'record/list'},
                    fn ($item) => $item !== self::VIEW_SETUP_HANDLER
                )
            );
            $this->metadata->saveCustom('clientDefs', $entityType, $clientDefs);
        }

        $saveRecordDefs = false;
        $recordDefs = $this->metadata->getCustom('recordDefs', $entityType) ?? (object) [];
        $recordDefs->listLoaderClassNameList ??= [];
        if (in_array(HasUnreadNotifications::class, $recordDefs->listLoaderClassNameList)) {
            $recordDefs->listLoaderClassNameList = array_values(
                array_filter(
                    $recordDefs->listLoaderClassNameList,
                    fn ($item) => $item !== HasUnreadNotifications::class
                )
            );
            $saveRecordDefs = true;
        }
        $recordDefs->beforeReadHookClassNameList ??= [];
        if (in_array(ReadUnreadNotifications::class, $recordDefs->beforeReadHookClassNameList)) {
            $recordDefs->beforeReadHookClassNameList = array_values(
                array_filter(
                    $recordDefs->beforeReadHookClassNameList,
                    fn ($item) => $item !== ReadUnreadNotifications::class
                )
            );
            $saveRecordDefs = true;
        }

        if ($saveRecordDefs) {
            $this->metadata->saveCustom('recordDefs', $entityType, $recordDefs);
        }
    }
}
