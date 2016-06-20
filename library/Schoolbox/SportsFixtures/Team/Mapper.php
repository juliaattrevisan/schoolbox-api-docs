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
use Schoolbox\SportsFixtures\Team\Model as TeamModel;

/**
 * Class Mapper
 * @method Model createModel()
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
        $this->hasModel(TeamModel::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('division_id', SportMapper::class, 'id', null, null, 'division', 'division');
        $this->uses('name', self::TYPE_STRING);
        $this->uses('url', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->hasSoftDeleteDateField('deleted_at');

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'deletePlayers'));
    }

    /**
     * Delete all Players associated with this Team
     * when it's being deleted
     *
     * @param Model $team
     */
    public function deletePlayers(Model $team)
    {
        /* TODO: Delete all Players in this Team when this Team is deleted
         */

        /* TODO: Delete all Fixtures in this Team when this Team is deleted
         */
    }
}