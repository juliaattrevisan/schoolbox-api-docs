<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Fixtures\Season;


use Alaress\Database\Predicate\Builder;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Match\Mapper as MatchMapper;
use Schoolbox\Fixtures\Season\Model as Season;
use Schoolbox\Mapper as BaseMapper;


/**
 * Class Mapper
 * @method Season createModel($idValue = null)
 * @method Season findById($identifierValue)
 * @package Schoolbox\Fixtures\Season
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
        $this->uses('archived_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, array($this, 'deleteDivisions'));
        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, array($this, 'deleteMatches'));
    }

    /**
     * Soft-Delete all Divisions associated with this Season when it's being deleted
     *
     * @param Season $season
     */
    public function deleteDivisions(Season $season)
    {
        $allDivisions = DivisionMapper::getInstance()->findByColumn('season_id', $season->getId(), ['cache' => false]);

        foreach ($allDivisions as $currentDivision) {
            DivisionMapper::getInstance()->delete($currentDivision);
        }
    }

    /**
     * Soft-Delete all Matches associated with this Season when it's being deleted
     *
     * @param Season $season
     */
    public function deleteMatches(Season $season)
    {
        $allMatches = MatchMapper::getInstance()->findByColumn('season_id', $season->getId());

        foreach ($allMatches as $currentMatch) {
            MatchMapper::getInstance()->delete($currentMatch);
        }
    }

    /**
     * Returns the number of divisions attached to each of the specified seasons.
     *
     * @param array|null $seasonIds      If supplied, apply filter
     * @param bool $getDeletedSeasons    Whether to retrieve deleted seasons
     * @param bool $getDeletedDivisions  Whether to count deleted divisions
     * @return int[]
     */
    public function getDivisionCounts(array $seasonIds = null, $getDeletedSeasons = false, $getDeletedDivisions = false)
    {

        $query = "
            SELECT s.id AS 'season_id', COUNT(d.id) AS 'division_count'
            FROM sportsfixtures_division d
            RIGHT JOIN sportsfixtures_season s ON s.id = d.season_id
            WHERE true";

        if(!$getDeletedDivisions){
            $query .= " AND ".DivisionMapper::getInstance()->getSoftDeleteSql(null, 'd');
        }

        if(!$getDeletedSeasons){
            $query .= " AND ".$this->getSoftDeleteSql(null, 's');
        }

        if(count($seasonIds)){
            $query .= " AND s.id IN ". Builder::getArrayPlaceholders($seasonIds);
        }

        $query .= " GROUP BY s.id";

        $statement = $this->database->prepare($query);

        $statement->execute(count($seasonIds) ? Builder::getArrayParameters($seasonIds) : null);

        $seasonDivisionCounts = [];
        while($row = $statement->fetch(\PDO::FETCH_ASSOC)){
            $seasonDivisionCounts[$row['season_id']] = $row['division_count'];
        }

        return $seasonDivisionCounts;
    }

    /**
     * Returns the number of matches attached to each of the specified seasons.
     *
     * @param array|null $seasonIds      If supplied, apply filter
     * @param bool $getDeletedSeasons    Whether to retrieve deleted seasons
     * @param bool $getDeletedMatches    Whether to count deleted matches
     * @return int[]
     */
    public function getMatchCounts(array $seasonIds = null, $getDeletedSeasons = false, $getDeletedMatches = false)
    {

        $query = "
            SELECT s.id AS 'season_id', COUNT(m.id) AS 'match_count'
            FROM sportsfixtures_match m
            RIGHT JOIN sportsfixtures_season s ON s.id = m.season_id
            WHERE true";

        if(!$getDeletedMatches){
            $query .= " AND ".MatchMapper::getInstance()->getSoftDeleteSql(null, 'm');
        }

        if(!$getDeletedSeasons){
            $query .= " AND ".$this->getSoftDeleteSql(null, 's');
        }

        if(count($seasonIds)){
            $query .= " AND s.id IN ". Builder::getArrayPlaceholders($seasonIds);
        }

        $query .= " GROUP BY s.id";

        $statement = $this->database->prepare($query);

        $statement->execute(count($seasonIds) ? Builder::getArrayParameters($seasonIds) : null);

        $seasonMatchCounts = [];
        while($row = $statement->fetch(\PDO::FETCH_ASSOC)){
            $seasonMatchCounts[$row['season_id']] = $row['match_count'];
        }

        return $seasonMatchCounts;
    }




}
