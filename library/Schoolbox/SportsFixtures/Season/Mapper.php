<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Season;

use Schoolbox\Mapper As BaseMapper;
use Schoolbox\SportsFixtures\Season\Model as Season;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;


/**
 * Class Mapper
 * @method Season createModel(int $idValue = null)
 * @method Season findById($identifierValue)
 * @package Schoolbox\SportsFixtures\Season
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_season');
        $this->hasModel(Season::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('start', self::TYPE_DATE);
        $this->uses('end', self::TYPE_DATE);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'deleteDivisions'));
    }

    /**
     * Soft-Delete all Divisions associated with this Season when it's being deleted
     *
     * @param Season $season
     */
    public function deleteDivisions(Season $season)
    {
        $allDivisions = DivisionMapper::getInstance()->findByColumn('season_id', $season->getId());

        foreach ($allDivisions as $currentDivision) {
            DivisionMapper::getInstance()->delete($currentDivision);
        }
    }

    /**
     * Find all the Seasons whose names are like $keyword
     *
     * @param string $keyword
     * @return \Schoolbox\Collection
     * @throws MapperException
     */
    public function findByName($keyword)
    {
        return $this->findByColumn('~name', "%{$this->database->quoteForLike($keyword)}%");
    }

}
