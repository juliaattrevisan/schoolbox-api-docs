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
use Schoolbox\SportsFixtures\Coach;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use Schoolbox\SportsFixtures\Team\Model as Team;

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
        $this->joins('division_id', DivisionMapper::class, 'id', null, null, 'division', 'division');
        $this->uses('name', self::TYPE_STRING);
        $this->uses('url', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joinsVia(
            'sportsfixtures_team_coach',
            Coach\Mapper::class,
            'id',
            'sportsfixtures_coach_id',
            'sportsfixtures_team_id',
            null,
            null,
            null,
            'coaches'
        );

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
        return $this->findByColumnList([
            'division_id' => $divisionId,
            '~name'       => "%{$this->database->quoteForLike($keyword)}%"
        ]);
    }

    /**
     * Delete all Players associated with this Team
     * when it's being deleted
     *
     * @param Team $team
     */
    public function deletePlayers(Team $team)
    {
        $teamUserMapper = User\Mapper::getInstance();
        $teamUserMapper->delete($teamUserMapper->findByColumn('team_id', $team->getId()));

        /* TODO: Delete all Fixtures in this Team when this Team is deleted
         */
    }
}
