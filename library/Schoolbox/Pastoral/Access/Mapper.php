<?php
/**
 * Pastoral care access control
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\Access;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;
use Schoolbox\RoleMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_access');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('pastoral_id', self::TYPE_INTEGER);
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('role_id', RoleMapper::class, 'id', null, null, 'role', 'role');
        $this->hasSoftDeleteDateField('deleted_at');
    }


    /**
     * Return the first part of the string required to select from this mapper
     *
     * @throws BaseMapper\DefinitionException
     * @return string
     */
    protected function getSelectString()
    {
        return "
			SELECT
				{$this->getColumnString()},
                {$this->getJoinColumnString('role', 'r')},
                {$this->getJoinColumnString('user', 'u')}
			FROM
				`{$this->getTable()}`
                LEFT JOIN {$this->getJoinSql('role', '', 'r')}
                LEFT JOIN {$this->getJoinSql('user', '', 'u')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @throws Exception
     * @return GenericCollection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            if (empty($row['u.id']) && empty($row['r.id'])) {
                // this record is invalid and should be deleted; skip
                continue;
            }

            /** @var Model $access */
            $access = $this->hydrate($row);

            if ($row['u.id']) {
                /** @noinspection PhpParamsInspection */
                $access->setUser($this->hydrateJoin($row, 'user', 'u'));
            }

            if ($row['r.id']) {
                /** @noinspection PhpParamsInspection */
                $access->setRole($this->hydrateJoin($row, 'role', 'r'));
            }
        }

        return $c;
    }


    /**
     * Retrieves all access relating to a particular pastoral id
     *
     * @param  int $pastoralId
     * @return GenericCollection
     */
    public function findByPastoralId($pastoralId)
    {
        return $this->findByColumn('pastoral_id', $pastoralId);
    }
}