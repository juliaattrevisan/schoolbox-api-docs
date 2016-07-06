<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Coach;

use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;
use Schoolbox\User as UserModel;

/**
 * The mapper for a sports fixtures coach.
 *
 * @method Model createModel()
 * @package Schoolbox\SportsFixtures\Coach
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_coach');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Hydrate user as well by default.
     *
     * @param \PDOStatement $statement
     * @return Collection
     * @throws BaseMapper\Exception
     */
    public function hydrateSelect(\PDOStatement $statement)
    {
        $collection = new Collection();
        foreach ($statement as $row) {
            $model = $this->hydrate($row);
            if (isset($row['u.id'])) {
                $user = $this->hydrateJoin($row, 'user', 'u');
                if ($user instanceof UserModel) {
                    $model->setUser($user);
                }
            }
            $collection->add($model);
        }
        return $collection;
    }

    /**
     * Retrieves a list of items for a tag cloud.
     *
     * @param string $search
     * @return Collection|Model[]
     */
    public function findForTagCloud($search)
    {
        if (strlen($search) === 0) {
            return new Collection();
        }
        // Join side: tag w/o user is okay, so is user w/o tag - therefore FULL OUTER JOIN.
        // But that doesn't exist in MySQL! Emulate with LEFT JOIN U RIGHT JOIN
        // Search match: close enough to user AJAX search
        $selectPart = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('user', 'u')}
            FROM {$this->getTable()}
        ";
        $wherePart = "
            WHERE {$this->getTable()}.name LIKE :search
            OR    CONCAT(u.firstname, ' ', u.lastname) LIKE :search
        ";
        $query = "
            ($selectPart LEFT JOIN {$this->getJoinSql('user', '', 'u')} $wherePart)
            UNION ALL
            ($selectPart RIGHT JOIN {$this->getJoinSql('user', '', 'u')} $wherePart)
            -- ORDER BY u. ASC, {$this->getTable()}.name ASC
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(['search' => $this->getDatabase()->quoteForLike($search).'%']);
        return $this->hydrateSelect($statement);
    }
}
