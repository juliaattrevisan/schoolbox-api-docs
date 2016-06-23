<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Sport;


use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use \Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use \Schoolbox\SportsFixtures\Position\Mapper as PositionMapper;

/**
 * Class Mapper
 * @method Model createModel()
 * @method Model findById($id)
 * @package Schoolbox\SportsFixtures\Sport
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_sport');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('url', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'deleteDivisions'));
    }

    /**
     * Delete all Divisions associated with this Sport
     * when it's being deleted
     *
     * Delete all Positions associated with this Sport
     * when it's being deleted
     *
     * @param Model $sport
     */
    public function deleteDivisions(Model $sport)
    {
        $allDivisions = DivisionMapper::getInstance()->findByColumn('sport_id', $sport->getId());

        foreach ($allDivisions as $currentDivision) {
            DivisionMapper::getInstance()->delete($currentDivision);
        }

        $allPositions = PositionMapper::getInstance()->findByColumn('sport_id', $sport->getId());

        foreach ($allPositions as $currentPosition) {
            PositionMapper::getInstance()->delete($currentPosition);
        }
    }
}