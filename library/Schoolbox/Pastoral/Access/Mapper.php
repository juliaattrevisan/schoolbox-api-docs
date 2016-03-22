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

use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\RoleMapper;

class Mapper extends BaseMapper
{
    protected function mapping()
    {
        $this->hasTable('pastoral_access');
        $this->hasPrototype(new Model());
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('pastoral_id', self::TYPE_INTEGER);
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user');
        $this->joins('role_id', RoleMapper::class, 'id', null, null, 'role');
        $this->hasSoftDeleteDateField('deleted_at');
    }

    /**
     * Retrieves all access relating to a particular pastoral id
     *
     * @param  int $pastoralId
     * @return \Schoolbox\Collection
     */
    public function findByPastoralId($pastoralId)
    {
        $query = "
			SELECT
				{$this->getColumnString('r')},
                {$this->getJoinColumnString('role', 'rl')},
                {$this->getJoinColumnString('user', 'u')}
			FROM
				`{$this->getTable()}` `r`
                LEFT JOIN {$this->getJoinSql('role', 'r', 'rl')}
                LEFT JOIN {$this->getJoinSql('user', 'r', 'u')}
			WHERE
				`r`.`deleted_at` IS NULL AND
				`r`.`pastoral_id` = :pastoralId
			";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':pastoralId', $pastoralId);
        $statement->execute();

        $collection = new Collection();

        foreach ($statement as $row) {
            $pastoral = $this->hydrate($row, 'a');
            $pastoral->setRole($this->hydrateJoin($row, 'role', 'r'));
            $pastoral->setUser($this->hydrateJoin($row, 'user', 'u'));
            $collection->add($pastoral);
        }

        return $collection;
    }
}