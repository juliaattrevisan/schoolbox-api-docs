<?php
/**
 * Mapper for rubric usage.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use PDO;
use PDOStatement;
use Schoolbox;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;

/**
 * Class DescriptorMapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 * @method Descriptor createModel($idValue = null)
 * @method Descriptor[]|GenericCollection findByColumn($columnName, $columnValue, $options = array())
 * @method Descriptor findOneByColumn($columnName, $columnValue, $order = array(), $options = array())
 * @method Descriptor hydrate(array $row, $alias = '', $callback = null)
 */
class DescriptorMapper extends Schoolbox\Mapper {

    protected function mapping() {
        $this->hasTable('rubric_descriptor');
        $this->hasModel(Descriptor::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('criterion_id', CriterionMapper::class, 'id', null, null, 'criterion', 'criterion');
        $this->joins('level_id', LevelMapper::class, 'id', null, null, 'level', 'level');
        $this->uses('value', self::TYPE_DECIMAL);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreRubricLevel']);
    }


    /**
     * Ensure that if we restore a descriptor, its level does not remain deleted.
     *
     * This is required because deletion of a criterion may have triggered deletion
     * of the last descriptor in a level. If we restore that criterion, it will restore
     * the descriptor, which should restore the level.
     *
     * If the descriptor was not the last descriptor deleted in some level, it does
     * no harm to restore its level.
     *
     * Note that this restoration is outside the scope of the RecursiveUndeleter as this is
     * not a simple deletion-cascade from parent to child, rather it is from a child to parent.
     *
     * @param Descriptor $descriptor
     * @return bool
     * @throws Schoolbox\Mapper\DefinitionException
     */
    public function restoreRubricLevel(Descriptor $descriptor)
    {
        /** @var LevelMapper $levelMapper */
        $levelMapper = $this->getJoinMapper('level');

        try {
            // Ensure dates are hydrated (not always guaranteed)
            $level = $levelMapper->findById($descriptor->getLevel()->getId());
        } catch (Schoolbox\Mapper\Exception\Deleted $e) {
            $level = $e->getDeletedObject();
        }
        if ($level->isDeleted()) {
            $this->getJoinMapper('level')->restoreSoftDeleted($level);
        }
        return true;
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT  {$this->getColumnString()},
                    {$this->getJoinColumnString('level', 'l')},
                    {$this->getJoinColumnString('criterion', 'c')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('level', '', 'l', true)}
            INNER JOIN {$this->getJoinSql('criterion', '', 'c', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $levelMapper     = $this->getJoinMapper('level');
        $criterionMapper = $this->getJoinMapper('criterion');

        /* All indexed by primary key */
        $descriptors     = $this->createCollection();
        $levels          = $levelMapper->createCollection();
        $criteria        = $criterionMapper->createCollection();

        // Row keys for de-duplication
        $levelKey        = "l.{$levelMapper->getIdentifierColumn()}";
        $criterionKey    = "c.{$criterionMapper->getIdentifierColumn()}";

        /** @var Level $level */
        $level = null;

        /** @var Criterion $criterion */
        $criterion = null;

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {

            $levelId = $r[$levelKey];
            $criterionId = $r[$criterionKey];

            // First time we've encountered this Level ?
            if(!$levels->getOffset($levelId)){
                $levels->setOffset($levelId, $this->hydrateJoin($r, 'level', 'l'));
            }
            $level = $levels->getOffset($levelId);

            // First time we've encountered this Criterion ?
            if(!$criteria->getOffset($criterionId)){
                $criteria->setOffset($criterionId, $this->hydrateJoin($r, 'criterion', 'c'));
            }
            $criterion = $criteria->getOffset($criterionId);

            $descriptor = $this->hydrate($r);
            $descriptors->setOffset($descriptor->getId(), $descriptor);

            // Link to Level
            $descriptor->setLevel($level);
            $level->getDescriptors()->setOffset($descriptor->getId(), $descriptor);

            // Link to Criterion
            $descriptor->setCriterion($criterion);
        }

        return $descriptors;
    }


    /**
     * Clones descriptors from one submission box to another.
     *
     * @param int  $from_id         The ID of the submission box to copy from.
     * @param int  $to_id           The ID of the submission box to copy to.
     * @param bool $createCriterion If true, create missing criteria
     *
     * @return int[] A list of new rubric descriptors
     * @deprecated
     * @see RubricMapper::copyRubric()
     */
    public function copyFromCopiedSubmissionBox($from_id, $to_id, $createCriterion = false) {

        // Get all descriptors and criteria for the from box
        $query = "
            SELECT
                {$this->getColumnString('rd')},
                {$this->getJoinColumnString('criterion', 'rc')},
                {$this->getJoinColumnString('level', 'rl')}
            FROM `{$this->getTable()}` `rd`
            INNER JOIN {$this->getJoinSql('criterion', 'rd', 'rc')}
            INNER JOIN {$this->getJoinSql('level', 'rd', 'rl')}
            WHERE `rl`.`submission_box_id` = :box_id
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(['box_id' => $from_id]);

        /** @var Descriptor[] $fromList */
        $fromList = $this->hydrateList($statement, 'rd', function($mapper, $model, $row) {
            /** @var DescriptorMapper $mapper */
            /** @var Descriptor $model */

            /** @var Criterion $criterion */
            $criterion = $mapper->getJoinMapper('criterion')->hydrate($row, 'rc');
            $model->setCriterion($criterion);

            /* @var Level $level */
            $level = $mapper->getJoinMapper('level')->hydrate($row, 'rl');
            $model->setLevel($level);
            return $model;
        });

        /** @var Level[]|Schoolbox\Collection $toLevels */
        $toLevels = $this->getJoinMapper('level')->findByColumn('box_id', $to_id);

        // And copy each descriptor in the from box into the to box
        /** @var SubmissionBox $toBox */
        $toBox  = SubmissionBoxMapper::getInstance()->findById($to_id);
        $toList = new Schoolbox\Collection();
        foreach ($fromList as $fromItem) {
            // The to box descriptor's criterion:
            // has the same name, type and grouping as the to box, but instead
            // belongs to the to box's category and level.
            $params = array(
                'parent_id'   => $toBox->getCategory()->getId(),
                'parent_type' => Criterion::PARENT_TYPE_CATEGORY,
                'name'        => $fromItem->getCriterion()->getName(),
                'grouping'    => $fromItem->getCriterion()->getGrouping(),
            );

            /** @var Criterion $toCriterion */
            try {
                $toCriterion = $this->getJoinMapper('criterion')->findOneByColumnList($params);
            } catch (Schoolbox\Mapper\Exception $e) {
                // Try creating a new one?
                if (!$createCriterion) {
                    continue;
                }
                $toCriterion = $this->getJoinMapper('criterion')->createModel();
                $toCriterion
                    ->setParentId($toBox->getCategory()->getId())
                    ->setParentType(Criterion::PARENT_TYPE_CATEGORY)
                    ->setName($fromItem->getCriterion()->getName())
                    ->setGrouping($fromItem->getCriterion()->getGrouping())
                ;
                $this->getJoinMapper('criterion')->save($toCriterion);
            }

            // The criterion's level:
            // has the same sequence# as the original, but belongs to the new level.
            $toLevel      = null;
            $fromSequence = $fromItem->getLevel()->getSequence();
            foreach ($toLevels as $level) {
                if ($level->getSequence() == $fromSequence) {
                    $toLevel = $level;
                    break;
                }
            }
            // Level doesn't exist? Build it
            if (!$toLevel instanceof Level) {
                $toLevel = clone $fromItem->getLevel();
                $toLevel->setSubmissionBox($toBox);
                $this->getJoinMapper('level')->create($toLevel);
                $toLevels->add($toLevel);
            }

            $toItem = clone $fromItem;
            $toItem
                ->setLevel($toLevel)
                ->setCriterion($toCriterion);
            $toList->add($toItem);
        }
        return $this->create($toList);
    }


    /**
     * No notifications.
     *
     * @param object $object
     * @param string $verb
     * @param object $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }

}
