<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Division\User;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\UserMapper;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;

/**
 * Mapper for the relation between divisions and users.
 *
 * @method Model createModel()
 * @method Model findById($id)
 * @package Schoolbox\SportsFixtures\Division\User
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_division_user');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('division_id', DivisionMapper::class, 'id', null, null, 'division', 'division');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('sequence', self::TYPE_INTEGER);
    }

    /**
     * This mapper's find* methods always hydrate the user and division.
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('user', 'u')},
                {$this->getJoinColumnString('division', 'd')}
            FROM {$this->database->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('user', '', 'u')}
            INNER JOIN {$this->getJoinSql('division', '', 'd')}
        ";
    }

    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param  PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = new Collection();
        foreach ($statement as $row) {
            $model = $this->createModel()
                ->setId($row['id'])
                ->setUser($this->hydrateJoin($row, 'user', 'u'))
                ->setDivision($this->hydrateJoin($row, 'division', 'd'))
                ->setSequence($row['sequence']);
            $collection->add($model);
        }
        return $collection;
    }
}
