<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event\Overrides;

use Schoolbox\GenericCollection;

/**
 * @method Model findById($id)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 * @method GenericCollection findByColumn($columnName, $columnValue, array $options = [])
 * @method GenericCollection findByColumnList($columnList, array $options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('sis_event_user_overrides');
        $this->hasModel(Model::class);
        $this->uses('event_id', self::TYPE_INTEGER);
        $this->uses('action', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);

        $this->joins('external_id', \Schoolbox\UserMapper::class, 'synergy_id', null, null, 'user', 'externalId');
    }


    /**
     * @inheritDoc
     *
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('externalId', 'u')},
                {$this->getJoinColumnString('externalId.role', 'r')}
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('externalId', '', 'u')}
            LEFT JOIN {$this->getJoinSql('externalId.role', 'u', 'r')}
        ";
    }


    /**
     * @inheritDoc
     *
     * @param \PDOStatement $statement
     * @return \Schoolbox\GenericCollection
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $stack = $this->createCollection();

        foreach ($statement as $row) {
            $override = $this->hydrate($row);

            /* This corresponds to a 'clear' mutation in the overrides
             * It has no user relationships
             */
            if (Model::CLEAR === $override->getAction()) {
                $stack->add($override);
                continue;
            }

            /** @var \Schoolbox\User|null $user */
            $user = $this->hydrateJoin($row, 'externalId', 'u');

            if ($user) {
                /** @var \Schoolbox\Role $role */
                $role = $this->hydrateJoin($row, 'externalId.role', 'r');

                /* The timetable doesn't involve parents. externalIds might end up
                 * being shared among multiple roletypes, but we can specifically
                 * exclude irrelevant roletypes here
                 */
                if ($role->isStudent() || $role->isStaff()) {
                    $user->setRole($role);

                    $override->setUser($user);

                    $stack->add($override);
                }
            }
        }

        return $stack;
    }


    /**
     * Find an in-order list of overrides for a given event identifier
     * This returns the mutations in explicitly order of 'sequence'
     * so you can't get execute them in an unintended order e.g.
     * relying on the db to push them out in the way you had thought
     *
     * @param int $eventId
     * @return GenericCollection
     */
    public function findForEvent(int $eventId): GenericCollection
    {
        return $this->findByColumn('event_id', $eventId, [
            'order' => [
                'sequence' => 'asc'
            ]
        ]);
    }
}
