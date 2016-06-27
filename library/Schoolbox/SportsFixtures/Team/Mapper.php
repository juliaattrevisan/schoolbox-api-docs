<?php
/**
 * Team Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team;

use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;
use Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use Schoolbox\SportsFixtures\Team\Model as Team;
use Schoolbox\Collection;

/**
 * Class Mapper
 * @method Team createModel()
 * @method Team findById($id)
 * @package Schoolbox\SportsFixtures\Division
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_team');
        $this->hasModel(Team::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('division_id', SportMapper::class, 'id', null, null, 'division', 'division');
        $this->uses('name', self::TYPE_STRING);
        $this->uses('url', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'deletePlayers'));
    }

    /**
     * Find all the teams whose names are like $keyword
     *
     * @param int $divisionId
     * @param string $keyword
     * @return \Schoolbox\Collection
     * @throws Exception
     */
    public function findByName($divisionId, $keyword)
    {
        $query = "
            SELECT
                {$this->getColumnString('t')}
            FROM {$this->getTable()} t
			WHERE t.name LIKE :nameLike
			AND division_id = :divisionId
        ";

        /* Don't know why codeLike contained / and not a trailing %
         */
        $params = [
            'nameLike' => "%{$this->database->quoteForLike($keyword)}%",
            'divisionId' => $divisionId
        ];

        $statement = $this->database->prepare($query);
        $statement->execute($params);

        $hashtable = array();

        /* Only need to return category records
         */
        foreach ($statement as $row) {
            $team = $this->hydrate($row, 't');

            $hashtable[$team->getId()] = $team;
        }

        return new Collection(array_values($hashtable));
    }

    /**
     * Delete all Players associated with this Team
     * when it's being deleted
     *
     * @param Team $team
     */
    public function deletePlayers(Team $team)
    {
        /* TODO: Delete all Players in this Team when this Team is deleted
         */

        /* TODO: Delete all Fixtures in this Team when this Team is deleted
         */
    }
}