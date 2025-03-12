<?php

namespace Espo\Modules\NotifEye\Classes\RecordHooks;

use Espo\Core\Record\Hook\ReadHook;
use Espo\Core\Record\ReadParams;
use Espo\Entities\Note;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\Modules\NotifEye\Tools\NotifEye\Helper;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Join;


/**
 * @implements ReadHook<Entity>
 * @noinspection PhpUnused
 */
class ReadUnreadNotifications implements ReadHook
{
    public function __construct(
        private Helper $helper,
        private EntityManager $entityManager,
        private User $user
    ) {}

    /**
     * @inheritDoc
     */
    public function process(Entity $entity, ReadParams $params): void
    {
        if (!$this->helper->isToUseNotifEyeFunctionality($entity->getEntityType())) {
            return;
        }

        $subQuery = $this->entityManager
            ->getQueryBuilder()
            ->select(Attribute::ID)
            ->from(Notification::ENTITY_TYPE)
            ->leftJoin(
                Join::create(Note::ENTITY_TYPE, 'n')
                    ->withConditions(
                        Cond::and(
                            Cond::equal(
                                Cond::column('relatedId'),
                                Cond::column('n.' . Attribute::ID)
                            ),
                            Cond::equal(
                                Cond::column('relatedType'),
                                Note::ENTITY_TYPE
                            ),
                            Cond::equal(
                                Cond::column('n.' . Attribute::DELETED),
                                false
                            )
                        )
                    )
            )
            ->where(
                Cond::and(
                    Cond::equal(
                        Cond::column('read'),
                        false
                    ),
                    Cond::equal(
                        Cond::column('userId'),
                        $this->user->getId()
                    ),
                    Cond::or(
                        Cond::and(
                            Cond::equal(
                                Cond::column('relatedId'),
                                $entity->getId()
                            ),
                            Cond::equal(
                                Cond::column('relatedType'),
                                $entity->getEntityType()
                            )
                        ),
                        Cond::and(
                            Cond::equal(
                                Cond::column('relatedParentId'),
                                $entity->getId()
                            ),
                            Cond::equal(
                                Cond::column('relatedParentType'),
                                $entity->getEntityType()
                            )
                        ),
                        Cond::and(
                            Cond::equal(
                                Cond::column('n.parentId'),
                                $entity->getId()
                            ),
                            Cond::equal(
                                Cond::column('n.parentType'),
                                $entity->getEntityType()
                            )
                        )
                    )
                )
            )
            ->build();

        $updateQuery = $this->entityManager
            ->getQueryBuilder()
            ->update()
            ->in(Notification::ENTITY_TYPE)
            ->set(['read' => true])
            ->where(
                Cond::in(
                    Cond::column(Attribute::ID),
                    $subQuery
                )
            )
            ->build();
        $this->entityManager->getQueryExecutor()->execute($updateQuery);
    }
}
