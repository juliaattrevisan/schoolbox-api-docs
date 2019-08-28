<?php
/**
 * Mapper for rubric usage.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\Descriptor;

use PDO;
use PDOStatement;
use Schoolbox;
use Schoolbox\Assessment\Rubric\DataModel\Capability;
use Schoolbox\Assessment\Rubric\DataModel\Indicator;
use Schoolbox\Assessment\Rubric\DataModel\Level;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;

/**
 * Class DescriptorMapper
 *
 * @package Schoolbox\Assessment\Rubric\DataModel
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = array())
 * @method Model[]|GenericCollection findByColumnList($columnList, $options = [])
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends Schoolbox\Mapper {

    protected function mapping() {
        $this->hasTable('rubric_descriptor');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('rubric_indicator_id', Indicator\Mapper::class, 'id', null, null, 'indicator', 'indicator');
        $this->joins('level_id', Level\Mapper::class, 'id', null, null, 'level', 'level');
        $this->uses('max_value', self::TYPE_DECIMAL);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteEmptyLevel']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreRubricLevel']);
    }


    /**
     * @param Model $descriptor
     * @throws Schoolbox\Mapper\DefinitionException
     */
    public function deleteEmptyLevel(Model $descriptor)
    {
        /** @var Level\Mapper $levelMapper */
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
     * This is required because deletion of a indicator may have triggered deletion
     * of the last descriptor in a level. If we restore that indicator, it will restore
     * the descriptor, which should restore the level.
     *
     * If the descriptor was not the last descriptor deleted in some level, it does
     * no harm to restore its level.
     *
     * Note that this restoration is outside the scope of the RecursiveUndeleter as this is
     * not a simple deletion-cascade from parent to child, rather it is from a child to parent.
     *
     * @param Model $descriptor
     * @return bool
     * @throws Schoolbox\Mapper\DefinitionException
     */
    public function restoreRubricLevel(Model $descriptor)
    {
        /** @var Level\Mapper $levelMapper */
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
                    {$this->getJoinColumnString('indicator', 'c')},
                    {$this->getJoinColumnString('indicator.capability', 'g')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('level', '', 'l', true)}
            INNER JOIN {$this->getJoinSql('indicator', '', 'c', true)}
            INNER JOIN {$this->getJoinSql('indicator.capability', 'c', 'g', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Level\Mapper $levelMapper */
        $levelMapper      = $this->getJoinMapper('level');
        $indicatorMapper  = $this->getJoinMapper('indicator');
        $capabilityMapper = $this->getJoinMapper('indicator.capability');

        /* All indexed by primary key */
        $descriptors      = $this->createCollection();
        $levels           = $levelMapper->createCollection();
        $indicators       = $indicatorMapper->createCollection();
        $capabilities     = $capabilityMapper->createCollection();

        // Row keys for de-duplication
        $levelKey         = "l.{$levelMapper->getIdentifierColumn()}";
        $indicatorKey     = "c.{$indicatorMapper->getIdentifierColumn()}";
        $capabilityKey    = "g.{$capabilityMapper->getIdentifierColumn()}";

        /** @var Level\Model $level */
        $level = null;

        /** @var Indicator\Model $indicator */
        $indicator = null;

        /** @var Capability\Model $capability */
        $capability = null;

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {

            $levelId      = $r[$levelKey];
            $indicatorId  = $r[$indicatorKey];
            $capabilityId = $r[$capabilityKey];

            // First time we've encountered this Level ?
            if (!$levels->getOffset($levelId)) {
                $levels->setOffset($levelId, $levelMapper->hydrate($r, 'l')->setDescriptors($this->createCollection()));
            }
            $level = $levels->getOffset($levelId);

            // First time we've encountered this indicator?
            if (!$indicators->getOffset($indicatorId)) {
                // First time we've encountered this capability?
                if (!$capabilities->getOffset($capabilityId)) {
                    $capabilities->setOffset($capabilityId, $this->hydrateJoin($r, 'indicator.capability', 'g'));
                }

                // Set the capability as well
                /** @var Capability\Model $capability */
                /** @var Indicator\Model $indicator */
                $capability     = $capabilities->getOffset($capabilityId);
                $indicator = $this->hydrateJoin($r, 'indicator', 'c');
                $indicator->setCapability($capability);
                $indicators->setOffset($indicatorId, $indicator);
            }
            $indicator = $indicators->getOffset($indicatorId);

            $descriptor = $this->hydrate($r);
            $descriptors->setOffset($descriptor->getId(), $descriptor);

            // Link to Level
            $descriptor->setLevel($level);
            $level->getDescriptors()->setOffset($descriptor->getId(), $descriptor);

            // Link to indicator
            $descriptor->setIndicator($indicator);
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
