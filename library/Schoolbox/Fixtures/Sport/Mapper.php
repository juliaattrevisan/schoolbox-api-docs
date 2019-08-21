<?php
/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Sport;


use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper As BaseMapper;
use \Schoolbox\Fixtures\Sport\Model as Sport;
use \Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use \Schoolbox\Fixtures\Position\Mapper as PositionMapper;

/**
 * Class Mapper
 *
 * @package Schoolbox\Fixtures\Sport
 *
 * @method Sport createModel($idValue = null)
 * @method Sport findById($id)
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_sport');
        $this->hasModel(Sport::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('url', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->hasJoin('positions', [
            'multiple'  => true,
            'mapper'    => PositionMapper::class,
            'this_key'  => 'id',
            'other_key' => 'sport_id',
        ]);

        $this->registerCallback(self::CALLBACK_POSTSAVE,       [ $this, 'savePositions' ]);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [ $this, 'deleteDivisions' ]);
    }


    /**
     * Return the first part of the string required to select from this mapper
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT {$this->getColumnString()}, 
                   {$this->getJoinColumnString('positions', 'p')}
        ";
    }


    /**
     * Return the second part of the string required to select from this mapper
     *
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('positions', '', 'p')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Sport[] $cache */
        $cache = [];
        /** @var PositionMapper $positionMapper */
        $positionMapper = $this->getJoinMapper('positions');
        $positionIdCol  = "p.{$positionMapper->getIdentifierColumn()}";

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {

            $sportId = $r['id'];

            if (empty($cache[$sportId])) {
                $cache[$sportId] = $this->hydrate($r);
                $cache[$sportId]->setPositions($positionMapper->createCollection());
            }

            // Only add position/s if sport has some
            if (isset($r[$positionIdCol])) {
                $cache[$sportId]->getPositions()->add($this->hydrateJoin($r, 'positions', 'p'));
            }
        }

        return $this->createCollection($cache);
    }

    /**
     * Perform an intersection of the positions given against what exists in the database:
     *  - any records that exist in memory are to be persisted to the DB;
     *  - any records that exist in DB but not memory are to be deleted.
     *
     * @param Model $model
     * @return bool
     */
    public function savePositions(Sport $model)
    {
        if ($model->getPositions() === null) {
            // Don't attempt to save if join is not hydrated
            return true;
        }
        // ensure the positions all point onto this sport
        foreach ($model->getPositions() as $position) {
            $position->setSport($model);
        }

        return $this->cascadeJoin('positions', [ 'sport_id' => $model->getId() ], $model->getPositions());
    }

    /**
     * Delete all Divisions associated with this Sport
     * when it's being deleted
     *
     * Delete all Positions associated with this Sport
     * when it's being deleted
     *
     * @param Sport $sport
     */
    public function deleteDivisions(Sport $sport)
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
