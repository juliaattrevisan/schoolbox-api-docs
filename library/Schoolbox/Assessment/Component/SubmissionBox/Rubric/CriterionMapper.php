<?php
/**
 * Mapper for rubric criteria.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use PDOStatement;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Class CriterionMapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 * @method Criterion createModel($idValue = null)
 * @method Criterion findById($id)
 * @method Criterion[] findByColumnList($columnList, $options = array())
 * @method Criterion findOneByColumnList($columnList, $order = [], $options = [])
 */
class CriterionMapper extends BaseMapper {

    protected function mapping() {
        $this->hasTable('rubric_criterion');
        $this->hasModel(Criterion::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);

        // These fields now belong to rubric_group
//        $this->uses('grouping', self::TYPE_STRING);
//        $this->uses('parent_id', self::TYPE_INTEGER);
//        $this->uses('parent_type', self::TYPE_STRING);

        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joins('rubric_group_id', GroupMapper::class, 'id', null, null, 'group', 'group');

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
                {$this->getJoinColumnString('group', 'g')}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('group', '', 'g', true)}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        /** @var GroupMapper                   $groupMapper */
        /** @var NodeMapper                    $curriculumNodeMapper */
        /** @var GenericCollection|Criterion[] $collection */
        $groupMapper          = $this->getJoinMapper('group');
        $groupIds             = [];
        $curriculumNodeMapper = $this->getJoinMapper('curriculum_node_for_rubric_criterion');
        $collection           = $this->createCollection();
        foreach ($statement as $row) {
            /** @var Criterion $crit */
            $crit = $this->hydrate($row);
            if ($row['g.id']) {
                $groupIds[] = $row['g.id'];
                $crit->setGroup($groupMapper->hydrate($row, 'g'));
            }
            $collection[$crit->getId()] = $crit;
        }

        // Fetch curriculum nodes for criterion and group separately
        $criterionNodes = $curriculumNodeMapper->findForOtherMapper(
            $this,
            'curriculum_node_for_rubric_criterion',
            $collection->keys()
        );
        $groupNodes = $curriculumNodeMapper->findForOtherMapper(
            $groupMapper,
            'curriculum_node_for_rubric_group',
            $groupIds
        );
        foreach ($collection as $criterion) {
            if (isset($criterionNodes[$criterion->getId()])) {
                $criterion->setCurriculumNodes($criterionNodes[$criterion->getId()]);
            }
            if (isset($groupNodes[$criterion->getGroup()->getId()])) {
                $criterion->getGroup()->setCurriculumNodes($groupNodes[$criterion->getGroup()->getId()]);
            }
        }

        return $collection;
    }


    /**
     * Soft-delete callback to delete any descriptors for this criterion
     *
     * @param Criterion $criterion
     * @return bool
     */
    public function softDeleteDescriptors(Criterion $criterion)
    {
        DescriptorMapper::getInstance()->deleteByColumn('criterion_id', $criterion->getId());
        return true;
    }


    /**
     * Post-delete callback for this criterion.
     *
     * If there's no remaining non-deleted criteria for this group, delete the
     * group.
     *
     * @param Criterion $criterion
     * @return bool
     */
    public function deleteGroup(Criterion $criterion)
    {
        $remaining = $this->countByColumn('rubric_group_id', $criterion->getGroup()->getId());
        if (0 === $remaining) {
            $this->getJoinMapper('group')->delete($criterion->getGroup());
        }
        return true;
    }


    /**
     * Post-restore callback for this criterion.
     *
     * Undelete the criterion's group, if it's deleted.
     *
     * @param Criterion $criterion
     * @return bool
     */
    public function restoreGroup(Criterion $criterion)
    {
        if ($criterion->getGroup()->isDeleted()) {
            $this->getJoinMapper('group')->restoreSoftDeleted($criterion->getGroup());
        }
        return true;
    }


    /**
     * Post-delete callback for this criterion.
     *
     * Soft-deleting a criterion (hard) deletes all of its usages from any
     * rubrics.
     *
     * @param Criterion $criterion
     */
    public function deleteUsages(Criterion $criterion): void
    {
        CriterionUsageMapper::getInstance()->deleteByColumn('criterion_id', $criterion->getId());
    }


    /**
     * Pre-save callback - if a criterion with the same name already exists in
     * its group, then set its ID to the existing one (thus updating the
     * existing record) instead of saving a new one.
     *
     * This replaces the unique index on this table, which had to be removed
     * because MySQL max index size doesn't allow more than about ~330 UTF-8
     * characters.
     *
     * @param  Criterion $object
     * @return bool      Always true
     */
    public function verifyUniqueness(Criterion $object): bool
    {
        // normalize object's name for saving
        $object->setName($object->retrieveNormalizedName());

        // Lookup existing IDs
        $existingIds = $this->findIdsByColumnList([
            'rubric_group_id' => $object->getGroup()->getId(),
            'name'            => $object->getName(),
        ]);

        // found something? assign that ID if so
        if ($existingIds) {
            $object->setId(array_shift($existingIds));
        }

        return true;
    }


    /**
     * Find all criteria for a parent type.
     *
     * @param int|int[] $parentId   The parent ID (or list thereof) to find for
     * @param string    $parentType One of the `Group::PARENT_TYPE_*` constants
     * @param bool      $cache      If false, bypass result cache
     * @return GenericCollection|Criterion[]
     * @throws MapperException
     */
    public function findForParent($parentId, string $parentType, bool $cache = true): GenericCollection
    {
        return $this->findByColumnList(
            [
                'g.parent_id'   => $parentId,
                'g.parent_type' => $parentType,
            ],
            [
                'order' => ['g.name' => 'ASC', 'name' => 'ASC'],
                'cache' => $cache,
            ]
        );
    }


    /**
     * Find all criteria for a folder.
     *
     * @param int|int[] $folderId The folder ID (or list thereof) to find for
     * @param bool      $cache    If false, bypass result cache
     * @return GenericCollection|Criterion[]
     * @throws MapperException
     */
    public function findForFolder($folderId, bool $cache = true): GenericCollection
    {
        return $this->findForParent($folderId, Group::PARENT_TYPE_CATEGORY, $cache);
    }


    /**
     * Find a single criterion for a parent type, given its unique identifier
     * (parentId+parentType+name+groupName).
     *
     * @param int    $parentId   The parent ID (or list thereof) to find for
     * @param string $parentType One of the `Group::PARENT_TYPE_*` constants
     * @param string $name       The name of the criterion
     * @param string $groupName  The name of the criterion's group
     * @param bool   $cache      If false, bypass result cache
     * @return Criterion
     * @throws MapperException
     */
    public function findOneForParentByKey(
        int $parentId,
        string $parentType,
        string $name,
        string $groupName,
        bool $cache = true
    ): Criterion
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
     * Find a single criterion for a folder, given its unique identifier
     * (folder+name+groupName).
     *
     * @param int    $folderId  The folder ID (or list thereof) to find for
     * @param string $name      The name of the criterion
     * @param string $groupName The name of the criterion's group
     * @param bool   $cache     If false, bypass result cache
     * @return Criterion
     * @throws MapperException
     */
    public function findOneForFolderByKey(int $folderId, string $name, string $groupName, bool $cache = true): Criterion
    {
        return $this->findOneForParentByKey($folderId, Group::PARENT_TYPE_CATEGORY, $name, $groupName, $cache);
    }


    /**
     * Find a single criterion for a parent type, given its unique identifier
     * (parentId+parentType+name+groupName).
     *
     * If none exists: create it
     *
     * @param int    $parentId
     * @param string $parentType
     * @param string $name
     * @param string $groupName
     * @param bool   $cache
     * @return Criterion
     * @throws MapperException
     */
    public function findOrCreateOneForParentByKey(
        int $parentId,
        string $parentType,
        string $name,
        string $groupName,
        bool $cache = true
    ): Criterion
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
            /** @var GroupMapper $groupMapper */
            $groupMapper = $this->getJoinMapper('group');
            $group       = $groupMapper->findOrCreateOneForParentByName($parentId, $parentType, $groupName, $cache);
            $criterion   = $this->createModel()
                                ->setGroup($group)
                                ->setName($name);
            $this->save($criterion);
            return $criterion;
        }
    }


    /**
     * Find a single criterion for a folder, given its unique identifier
     * (folder+name+groupName).
     *
     * If none exists: create it
     *
     * @param int    $folderId
     * @param string $name
     * @param string $groupName
     * @param bool   $cache
     * @return Criterion
     * @throws MapperException
     */
    public function findOrCreateOneForFolderByKey(
        int $folderId,
        string $name,
        string $groupName,
        bool $cache = true
    ): Criterion
    {
        return $this->findOrCreateOneForParentByKey($folderId, Group::PARENT_TYPE_CATEGORY, $name, $groupName, $cache);
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
