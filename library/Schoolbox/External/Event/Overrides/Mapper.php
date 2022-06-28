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
        $this->uses('external_id', self::TYPE_STRING);
        $this->uses('action', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
    }


    /**
     * @inheritDoc
     *
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString()
    {
        $staff = \Schoolbox\Role::STAFF;
        $student = \Schoolbox\Role::STUDENT;

        /* Spoke with Mr Sannang about mappers not really understanding how to save
         * relational objects via a random column like synergy_id, so the usual trick
         * ->setUser((new \Schoolbox\User())->setExternalId('BRO')) doesn't work
         * the way it might have seemed (you can misuse mappers to save it, but that
         * is pretty dodgy)
         *
         * Therefore save external_id as a string and manually handcraft User instances
         * on their way out
         */
        $uColumnString = \Schoolbox\UserMapper::getInstance()->getColumnString('u');
        $rColumnString = \Schoolbox\RoleMapper::getInstance()->getColumnString('r');

        return "
            SELECT
                {$this->getColumnString()},
                {$uColumnString},
                {$rColumnString}
            FROM {$this->getTable()}
            LEFT JOIN `user` u ON external_id = u.synergy_id
            LEFT JOIN `groups` r ON u.group_id = r.id 
                AND r.group_type IN ('{$staff}', '{$student}')
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
        $userMapper = \Schoolbox\UserMapper::getInstance();
        $roleMapper = \Schoolbox\RoleMapper::getInstance();

        foreach ($statement as $row) {
            $override = $this->hydrate($row);

            /* This corresponds to a 'clear' mutation in the overrides
             * It has no user relationships
             */
            if (Model::CLEAR === $override->getAction()) {
                $stack->add($override);
                continue;
            }

            /* If the role is not null,
             * then we have a staff or student user we can utilise
             *
             * Parent and Guest roletypes spawn with r.id as null
             * but we can't exclude those in LEFT JOIN via getSelectString
             * because we'll lose the (null, 'clear') overrides.
             */
            if ($row['r.id']) {
                /** @var \Schoolbox\User|null $user */
                $user = $userMapper->hydrate($row, 'u');

                /** @var \Schoolbox\Role|null $role */
                $role = $roleMapper->hydrate($row, 'r');

                if ($user && $role) {
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
