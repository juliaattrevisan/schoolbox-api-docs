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
 * @method Descriptor findById($identifierValue)
 * @method Descriptor[]|GenericCollection findByColumn($columnName, $columnValue, $options = array())
 * @method Descriptor[]|GenericCollection findByColumnList($columnList, $options = [])
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
        $this->uses('max_value', self::TYPE_DECIMAL);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteEmptyLevel']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreRubricLevel']);
    }


    /**
     * @param Descriptor $descriptor
     * @throws Schoolbox\Mapper\Exception
     * @throws Schoolbox\Mapper\Exception\Deleted
     * @throws Schoolbox\Mapper\DefinitionException
     */
    public function deleteEmptyLevel(Descriptor $descriptor)
    {
        /** @var LevelMapper $levelMapper */
        $levelMapper = $this->getJoinMapper('level');

        // Find all descriptors whose level ID is the same as the level ID of the current level
        $currentLevelId = $descriptor->getLevel()->getId();
        $descriptorsInLevel = $this->findByColumn('level_id', $currentLevelId);
        $descriptorCount = $descriptorsInLevel->count();

        if ($descriptorCount === 0) {
            // No more descriptors exist in this level: delete the level
            $currentLevel = $descriptor->getLevel();
            $levelMapper->softDelete($currentLevel);
        }
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
                    {$this->getJoinColumnString('criterion', 'c')},
                    {$this->getJoinColumnString('criterion.group', 'g')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('level', '', 'l', true)}
            INNER JOIN {$this->getJoinSql('criterion', '', 'c', true)}
            INNER JOIN {$this->getJoinSql('criterion.group', 'c', 'g', true)}
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
        $groupMapper     = $this->getJoinMapper('criterion.group');

        /* All indexed by primary key */
        $descriptors     = $this->createCollection();
        $levels          = $levelMapper->createCollection();
        $criteria        = $criterionMapper->createCollection();
        $groups          = $groupMapper->createCollection();

        // Row keys for de-duplication
        $levelKey        = "l.{$levelMapper->getIdentifierColumn()}";
        $criterionKey    = "c.{$criterionMapper->getIdentifierColumn()}";
        $groupKey        = "g.{$groupMapper->getIdentifierColumn()}";

        /** @var Level $level */
        $level = null;

        /** @var Criterion $criterion */
        $criterion = null;

        /** @var Group $group */
        $group = null;

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {

            $levelId     = $r[$levelKey];
            $criterionId = $r[$criterionKey];
            $groupId     = $r[$groupKey];

            // First time we've encountered this Level ?
            if (!$levels->getOffset($levelId)) {
                $levels->setOffset($levelId, $this->hydrateJoin($r, 'level', 'l'));
            }
            $level = $levels->getOffset($levelId);

            // First time we've encountered this Criterion ?
            if (!$criteria->getOffset($criterionId)) {
                // First time we've encountered this group?
                if (!$groups->getOffset($groupId)) {
                    $groups->setOffset($groupId, $this->hydrateJoin($r, 'criterion.group', 'g'));
                }

                // Set the group as well
                /** @var Group $group */
                /** @var Criterion $criterion */
                $group     = $groups->getOffset($groupId);
                $criterion = $this->hydrateJoin($r, 'criterion', 'c');
                $criterion->setGroup($group);
                $criteria->setOffset($criterionId, $criterion);
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
