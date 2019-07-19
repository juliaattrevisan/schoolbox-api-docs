<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\Indicator;
use PDOStatement;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Mapper as CapabilityMapper;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Model as CapabilityModel;
use Schoolbox\Assessment\Rubric\DataModel\Descriptor\Mapper as DescriptorMapper;
use Schoolbox\Assessment\Rubric\DataModel\IndicatorUsage\Mapper as IndicatorUsageMapper;
use Schoolbox\Assessment\Rubric\Util;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Mapper for rubric indicators.
 *
 * @package Schoolbox\Assessment\Rubric\DataModel
 * @method Model createModel($idValue = null)
 * @method Model findById($id)
 * @method Model[] findByColumnList($columnList, $options = array())
 * @method Model findOneByColumnList($columnList, $order = [], $options = [])
 */
class Mapper extends BaseMapper {

    protected function mapping() {
        $this->hasTable('rubric_criterion');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joins('rubric_group_id', CapabilityMapper::class, 'id', null, null, 'capability', 'capability');

        // Add curriculum codes
        $this->joinsVia(
            'curriculum_node_for_rubric_criterion',
            NodeMapper::class,
            'id',
            'curriculum_node_id',
            'rubric_criterion_id',
            null,
            null,
            null,
            'curriculumNodes'
        );

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyUniqueness']);
        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteDescriptors']);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteGroup']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteUsages']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreGroup']);
    }


    /**
     * @inheritdoc
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('capability', 'g')}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('capability', '', 'g', true)}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        /** @var CapabilityMapper $groupMapper */
        /** @var NodeMapper                    $curriculumNodeMapper */
        /** @var GenericCollection|Model[] $collection */
        $groupMapper          = $this->getJoinMapper('capability');
        $groupIds             = [];
        $curriculumNodeMapper = $this->getJoinMapper('curriculum_node_for_rubric_criterion');
        $collection           = $this->createCollection();
        foreach ($statement as $row) {
            /** @var Model $crit */
            $crit = $this->hydrate($row);
            if ($row['g.id']) {
                $groupIds[] = $row['g.id'];
                $crit->setCapability($groupMapper->hydrate($row, 'g'));
            }
            $collection[$crit->getId()] = $crit;
        }

        // Fetch curriculum nodes for indicator and group separately
        $indicatorNodes = $curriculumNodeMapper->findForOtherMapper(
            $this,
            'curriculum_node_for_rubric_criterion',
            $collection->keys()
        );
        $groupNodes = $curriculumNodeMapper->findForOtherMapper(
            $groupMapper,
            'curriculum_node_for_rubric_group',
            $groupIds
        );
        foreach ($collection as $indicator) {
            if (isset($indicatorNodes[$indicator->getId()])) {
                $indicator->setCurriculumNodes($indicatorNodes[$indicator->getId()]);
            }
            if (isset($groupNodes[$indicator->getCapability()->getId()])) {
                $indicator->getCapability()->setCurriculumNodes($groupNodes[$indicator->getCapability()->getId()]);
            }
        }

        return $collection;
    }


    /**
     * Soft-delete callback to delete any descriptors for this indicator
     *
     * @param Model $indicator
     * @return bool
     */
    public function softDeleteDescriptors(Model $indicator)
    {
        DescriptorMapper::getInstance()->deleteByColumn('criterion_id', $indicator->getId());
        return true;
    }


    /**
     * Post-delete callback for this indicator.
     *
     * If there's no remaining non-deleted indicator for this group, delete the
     * group.
     *
     * @param Model $indicator
     * @return bool
     */
    public function deleteGroup(Model $indicator)
    {
        $remaining = $this->countByColumn('rubric_group_id', $indicator->getCapability()->getId());
        if (0 === $remaining) {
            $this->getJoinMapper('group')->delete($indicator->getCapability());
        }
        return true;
    }


    /**
     * Post-restore callback for this indicator.
     *
     * Undelete the indicator's group, if it's deleted.
     *
     * @param Model $indicator
     * @return bool
     */
    public function restoreGroup(Model $indicator)
    {
        if ($indicator->getCapability()->isDeleted()) {
            $this->getJoinMapper('capability')->restoreSoftDeleted($indicator->getCapability());
        }
        return true;
    }


    /**
     * Post-delete callback for this indicator.
     *
     * Soft-deleting a indicator (hard) deletes all of its usages from any
     * rubrics.
     *
     * @param Model $indicator
     */
    public function deleteUsages(Model $indicator): void
    {
        IndicatorUsageMapper::getInstance()->deleteByColumn('criterion_id', $indicator->getId());
    }


    /**
     * Pre-save callback.
     *
     * If a capability with the same name already exists in its indicator, then
     * block saving.
     *
     * This replaces the unique index on this table, which had to be removed
     * because MySQL max index size doesn't allow more than about ~330 UTF-8
     * characters.
     *
     * @param  Model $object
     * @return bool      Always true
     * @throws MapperException if the name isn't unique
     */
    public function verifyUniqueness(Model $object): bool
    {
        $pb = $this->getPredicateBuilder()
                   ->equals('rubric_group_id', $object->getCapability()->getId());

        // Don't compare this object with itself
        if ($object->getId()) {
            $pb->notEquals('id', $object->getId());
        }

        // Check this name isn't in use
        $existingNames  = $this->findScalarByPredicates('name', $pb);
        $normalizedName = Util::normalizeName($object->getName());
        foreach ($existingNames as $existingName) {
            if (Util::normalizeName($existingName) === $normalizedName) {
                throw new MapperException(
                    sprintf('Rubric indicator name "%s" is in use in its capability', $normalizedName)
                );
            }
        }

        return true;
    }


    /**
     * Find all indicators for a parent type.
     *
     * @param int|int[] $parentId   The parent ID (or list thereof) to find for
     * @param string    $parentType One of the `Group::PARENT_TYPE_*` constants
     * @param bool      $cache      If false, bypass result cache
     * @return GenericCollection|Model[]
     * @throws MapperException
     */
    public function findForParent($parentId, string $parentType, bool $cache = true): GenericCollection
    {
        return $this->findByColumnList(
            [
                'g.parent_id'   => $parentId,
                'g.parent_type' => $parentType,
                'g.deleted_at'  => null,
            ],
            [
                'order' => ['g.name' => 'ASC', 'name' => 'ASC'],
                'cache' => $cache,
            ]
        );
    }


    /**
     * Find all indicators for a folder.
     *
     * @param int|int[] $folderId The folder ID (or list thereof) to find for
     * @param bool      $cache    If false, bypass result cache
     * @return GenericCollection|Model[]
     * @throws MapperException
     */
    public function findForFolder($folderId, bool $cache = true): GenericCollection
    {
        return $this->findForParent($folderId, CapabilityModel::PARENT_TYPE_CATEGORY, $cache);
    }


    /**
     * Find a single indicator for a parent type, given its unique identifier
     * (parentId+parentType+name+groupName).
     *
     * @param int    $parentId   The parent ID (or list thereof) to find for
     * @param string $parentType One of the `Group::PARENT_TYPE_*` constants
     * @param string $name       The name of the indicator
     * @param string $groupName  The name of the indicator's group
     * @param bool   $cache      If false, bypass result cache
     * @return Model
     * @throws MapperException
     */
    public function findOneForParentByKey(
        int $parentId,
        string $parentType,
        string $name,
        string $groupName,
        bool $cache = true
    ): Model
    {
        return $this->findOneByColumnList(
            [
                'g.name'        => $groupName,
                'name'          => $name,
                'g.parent_type' => $parentType,
                'g.parent_id'   => $parentId,
            ],
            [],
            ['cache' => $cache,]
        );
    }


    /**
     * Find a single indicator for a folder, given its unique identifier
     * (folder+name+groupName).
     *
     * @param int    $folderId  The folder ID (or list thereof) to find for
     * @param string $name      The name of the indicator
     * @param string $groupName The name of the indicator's group
     * @param bool   $cache     If false, bypass result cache
     * @return Model
     * @throws MapperException
     */
    public function findOneForFolderByKey(int $folderId, string $name, string $groupName, bool $cache = true): Model
    {
        return $this->findOneForParentByKey($folderId, CapabilityModel::PARENT_TYPE_CATEGORY, $name, $groupName, $cache);
    }


    /**
     * Find a single indicator for a parent type, given its unique identifier
     * (parentId+parentType+name+groupName).
     *
     * If none exists: create it
     *
     * @param int    $parentId
     * @param string $parentType
     * @param string $name
     * @param string $groupName
     * @param bool   $cache
     * @return Model
     * @throws MapperException
     */
    public function findOrCreateOneForParentByKey(
        int $parentId,
        string $parentType,
        string $name,
        string $groupName,
        bool $cache = true
    ): Model
    {
        try {
            return $this->findOneForParentByKey($parentId, $parentType, $name, $groupName, $cache);

        } catch (MapperException\Deleted $e) {
            // It was deleted; restore it
            $item = $e->getDeletedObject();
            $this->restoreSoftDeleted($item);
            return $item;

        } catch (MapperException $e) {
            // Build it
            /** @var CapabilityMapper $groupMapper */
            $groupMapper = $this->getJoinMapper('capability');
            $group       = $groupMapper->findOrCreateOneForParentByName($parentId, $parentType, $groupName, $cache);
            $indicator   = $this->createModel()
                                ->setCapability($group)
                                ->setName($name);
            $this->save($indicator);
            return $indicator;
        }
    }


    /**
     * Find a single indicator for a folder, given its unique identifier
     * (folder+name+groupName).
     *
     * If none exists: create it
     *
     * @param int    $folderId
     * @param string $name
     * @param string $groupName
     * @param bool   $cache
     * @return Model
     * @throws MapperException
     */
    public function findOrCreateOneForFolderByKey(
        int $folderId,
        string $name,
        string $groupName,
        bool $cache = true
    ): Model
    {
        return $this->findOrCreateOneForParentByKey($folderId, CapabilityModel::PARENT_TYPE_CATEGORY, $name, $groupName, $cache);
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
