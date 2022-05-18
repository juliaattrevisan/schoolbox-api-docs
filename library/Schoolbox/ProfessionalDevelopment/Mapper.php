<?php

/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\ProfessionalDevelopment;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

class Mapper extends BaseMapper
{

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('user_professionaldevelopment');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('number', self::TYPE_STRING);
        $this->uses('registration_state', self::TYPE_STRING);
        $this->uses('accredited_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * @inheritdoc
     *
     * @return string
     * @throws BaseMapper\DefinitionException
     */
    protected function getSelectString()
    {
        $userMapper = $this->getJoinMapper('user');

        return "
            SELECT
                {$this->getColumnString()},
                {$userMapper->getColumnString('u')}
        ";
    }


    /**
     * @inheritdoc
     *
     * @return string
     * @throws BaseMapper\DefinitionException
     */
    protected function getFromString()
    {
        return "
            FROM `{$this->table}`
            LEFT JOIN {$this->getJoinSql('user', '', 'u', true)}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $userMapper = $this->getJoinMapper('user');

        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            try {
                $pd = $this->hydrate($row);

                if ($pd) {
                    $pd->setUser($userMapper->hydrate($row, 'u'));
                }

                $collection->add($pd);
            } catch (BaseMapper\Exception $e) {
                // noop, couldn't hydrate for some reason
            }
        }

        return $collection;
    }


    /**
     * Get the hardcoded list of states; derive it from the enum field
     *
     * @return array
     */
    public function getStates()
    {
        $query = '
            SELECT column_type
            FROM information_schema.COLUMNS
            WHERE table_schema = ?
            AND table_name = ?
            AND column_name = ?
        ';

        $args = [ SQL_db, $this->getTable(), 'registration_state' ];

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($args);

        if ($statement->rowCount() === 0) {
            return [''];
        }
        return explode(
            "','",
            preg_replace("/(enum|set)\('(.+?)'\)/", "\\2", $statement->fetchColumn()),
        );
    }


    /**
     * Get a list of states that have people registered in them
     */
    public function getAvailableStates()
    {
        return $this->findDistinct('registration_state');
    }
}
