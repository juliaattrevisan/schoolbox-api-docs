<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Position;

use Schoolbox\Collection;
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use Schoolbox\SportsFixtures\Position\Model as Position;
use Schoolbox\User;


/**
 * Class Mapper
 * @method Position createModel(int $idValue = null)
 * @package Schoolbox\SportsFixtures\Position
 */
class Mapper extends BaseMapper implements BaseMapper\UsableAsTagSource
{
    /**
     * Setup mapping
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_position');
        $this->hasModel(Position::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('sport_id', SportMapper::class, 'id', null, null, 'sport', 'sport');
        $this->uses('name', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * @param User $user
     * @param string $keyword
     * @param int $offset
     * @param int $limit
     * @return Collection|
     */
    public function findForTag(User $user, $keyword, $offset = 0, $limit = 0)
    {
        if (strlen($keyword) === 0) {
            return new Collection();
        }
        $query = "
            {$this->getSelectString()}
            WHERE name LIKE :search
            LIMIT :limit OFFSET :offset
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('search', '%'.$this->getDatabase()->quoteForLike($keyword).'%', \PDO::PARAM_STR);
        $statement->bindValue('limit', intval($limit), \PDO::PARAM_INT);
        $statement->bindValue('offset', intval($offset), \PDO::PARAM_INT);
        $statement->execute();
        return $this->hydrateSelect($statement);
    }
}
