<?php
/**
 * Divisions (eg. Soccer First, Seconds, Thirds) Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Division;

use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;
use Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use Schoolbox\SportsFixtures\Division\Model as Division;
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
/**
 * Class Mapper
 * @method Division createModel()
 * @method Model findById($id)
 * @package Schoolbox\SportsFixtures\Division
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_division');
        $this->hasModel(Division::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('sport_id', SportMapper::class, 'id', null, null, 'sport', 'sport');
        $this->uses('name', self::TYPE_STRING);
        $this->uses('url', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'deleteTeams'));
    }

    /**
     * Delete all Teams associated with this Division
     * when it's being deleted
     *
     * @param Division $division
     */
    public function deleteTeams(Division $division)
    {
        $allTeams = TeamMapper::getInstance()->findByColumn('division_id', $division->getId());

        foreach ($allTeams as $currentTeam) {
            TeamMapper::getInstance()->delete($currentTeam);
        }

        /* TODO: Delete all Players in this Division's Lobby when this Division is deleted
         */
    }
}