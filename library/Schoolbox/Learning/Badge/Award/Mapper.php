<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Award;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * @method Model findById($id)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 *
 * @package Schoolbox\Learning\Badge\Category
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('badge_user');
        $this->hasModel(Model::class);
        $this->joins('recipient_id', UserMapper::class, 'id', null, null, 'recipient', 'recipient');
        $this->joins('badge_id', \Schoolbox\Learning\Badge\Mapper::class, 'id', null, null, 'badge', 'badge');
        $this->uses('status', self::TYPE_STRING);
        $this->uses('level', self::TYPE_INTEGER);
        $this->joins('awarded_by', UserMapper::class, 'id', null, null, 'awardedBy', 'awardedBy');
        $this->uses('awarded_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('recipient', 'r')},
                {$this->getJoinColumnString('recipient.role', 'rr')},
                {$this->getJoinColumnString('awardedBy', 'a')},
                {$this->getJoinColumnString('awardedBy.role', 'ar')}
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('recipient', '', 'r', true)}
            INNER JOIN {$this->getJoinSql('recipient.role', 'r', 'rr')}
            INNER JOIN {$this->getJoinSql('awardedBy', '', 'a', true)}
            INNER JOIN {$this->getJoinSql('awardedBy.role', 'a', 'ar')}
        ";
    }


    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $badgeMapper = \Schoolbox\Learning\Badge\Mapper::getInstance();
        $collection = $this->createCollection();

        /* A lookup, because badge hydration is a dog
         */
        $badges = [];

        foreach ($statement as $row) {
            $badgeId = $row['badge_id'];
            $award = $this->hydrate($row);

            /* Set the $recipient and also their role
             */
            $recipient = $this->hydrateJoin($row, 'recipient', 'r');

            if ($recipient instanceof User) {
                $role = $this->hydrateJoin($row, 'recipient.role', 'rr');

                if ($role instanceof Role) {
                    $recipient->setRole($role);
                }

                $award->setRecipient($recipient);
            }

            /* Set the $awardedBy and also their role
             */
            $awardedBy = $this->hydrateJoin($row, 'awardedBy', 'a');

            if ($awardedBy instanceof User) {
                $role = $this->hydrateJoin($row, 'awardedBy.role', 'ar');

                if ($role instanceof Role) {
                    $awardedBy->setRole($role);
                }

                $award->setAwardedBy($awardedBy);
            }

            /* Instead of getting the Badge/Mapper's fields and hydration and stuff
             * just access them if we haven't already seen it; otherwise reuse the
             * one in the cache
             */
            if (!array_key_exists($badgeId, $badges)) {
                $badge = $badgeMapper->findById($badgeId);

                /* Save it for later
                 */
                $badges[$badgeId] = $badge;
            }

            $award->setBadge($badges[$badgeId]);

            $collection->add($award);
        }

        return $collection;
    }
}
