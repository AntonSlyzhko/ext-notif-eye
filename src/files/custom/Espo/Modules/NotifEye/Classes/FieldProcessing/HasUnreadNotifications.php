<?php

namespace Espo\Modules\NotifEye\Classes\FieldProcessing;

use Espo\Core\FieldProcessing\Loader;
use Espo\Core\FieldProcessing\Loader\Params;
use Espo\Entities\Note;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\Modules\NotifEye\Tools\NotifEye\Helper;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\Join;
use Espo\ORM\Query\Part\Selection;

/**
 * @implements Loader<Entity>
 * @noinspection PhpUnused
 */
class HasUnreadNotifications implements Loader
{
    public function __construct(
        private Helper $helper,
        private EntityManager $entityManager,
        private User $user
    ) {}

    /**
     * @inheritDoc
     */
    public function process(Entity $entity, Params $params): void
    {
        if (!$this->helper->isToUseNotifEyeFunctionality($entity->getEntityType())) {
            return;
        }

        $query = $this->entityManager
            ->getQueryBuilder()
            ->select(
                Selection::create(
                    Expr::greater(
                        Expr::count(Expr::column(Attribute::ID)),
                        0
                    )
                )
            )
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
            ->limit(0, 1)
            ->build();

        $hasUnreadNotifications = (bool) $this->entityManager->getQueryExecutor()->execute($query)->fetchColumn();
        $entity->set('hasUnreadNotifications', $hasUnreadNotifications);
    }
}
