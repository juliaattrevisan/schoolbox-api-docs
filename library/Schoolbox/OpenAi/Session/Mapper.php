<?php

/**
 * Mapper for the `ai_session` table — one row per AI session.
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model[]|GenericCollection createCollection($contents = [])
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritDoc
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('ai_session');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_STRING, self::COLUMN_IDENTIFIER_UUID);
        $this->uses('feature', self::TYPE_STRING, self::COLUMN_ENUM, null, array_column(Feature::cases(), 'value'));
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
    }


    /**
     * @inheritDoc
     *
     * @throws BaseMapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        $userMapper = $this->getJoinMapper('user');

        return "
            SELECT
                {$this->getColumnString()},
                {$userMapper->getColumnString('u')}
        ";
    }


    /**
     * @inheritDoc
     *
     * @throws BaseMapper\DefinitionException
     */
    protected function getFromString(): string
    {
        return "
            FROM `{$this->table}`
            LEFT JOIN {$this->getJoinSql('user', '', 'u', true)}
        ";
    }


    /**
     * @inheritDoc
     *
     * @param \PDOStatement $statement
     * @return Model[]|GenericCollection
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $userMapper = $this->getJoinMapper('user');

        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            try {
                $session = $this->hydrate($row);

                /** @var \Schoolbox\User $user */
                $user = $userMapper->hydrate($row, 'u');

                if ($user) {
                    $session->setUser($user);
                }

                $collection->add($session);
            } catch (BaseMapper\Exception $e) {
                // noop, couldn't hydrate for some reason
            }
        }

        return $collection;
    }
}
