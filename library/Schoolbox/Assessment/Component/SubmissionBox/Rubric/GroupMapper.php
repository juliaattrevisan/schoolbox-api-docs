<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use PDOStatement;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Mapper for rubric groups.
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 * @method Group createModel($idValue = null)
 * @method Group findById($id)
 * @method Group[] findByColumnList($columnList, $options = [])
 * @method Group findOneByColumnList($columnList, $order = [], $options = [])
 */
class GroupMapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable('rubric_group');
        $this->hasModel(Group::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        $this->uses('name', self::TYPE_STRING);
        $this->uses('parent_type', self::TYPE_STRING);

        // Parent ID can reference any of:
        // submission, category, curriculum
        // as far as I can tell, it's never not "category"
        $this->uses('parent_id', self::TYPE_INTEGER);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        // Add curriculum codes
        $this->joinsVia(
            'curriculum_node_for_rubric_group',
            NodeMapper::class,
            'id',
            'curriculum_node_id',
            'rubric_group_id',
            null,
            null,
            null,
            'curriculumNodes'
        );

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyUniqueness']);

//        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteCriteria']);
    }


    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        /** @var NodeMapper $curriculumNodeMapper */
        $curriculumNodeMapper = $this->getJoinMapper('curriculum_node_for_rubric_group');
        $collection           = $this->createCollection();
        foreach ($statement as $row) {
            /** @var Group $group */
            $group                       = $this->hydrate($row);
            $collection[$group->getId()] = $group;
        }

        // Fetch curriculum nodes separately
        $groupNodes = $curriculumNodeMapper->findForOtherMapper(
            $this,
            'curriculum_node_for_rubric_group',
            $collection->keys()
        );
        foreach ($collection as $group) {
            if (isset($groupNodes[$group->getId()])) {
                $group->setCurriculumNodes($groupNodes[$group->getId()]);
            }
        }

        return $collection;
    }


    /**
     * Pre-save callback - if a copy of this group already exists, update it
     * instead of saving a new one.
     *
     * This replaces the unique index on this table, which needs to be removed
     * because MySQL max index size doesn't allow more than about ~330 UTF-8
     * characters.
     *
     * @param  Group $object
     * @return bool  Always true
     */
    public function verifyUniqueness(Group $object)
    {
        // remove any newlines and consecutive/leading/trailing spaces from input
        $object->setName(preg_replace('`\s+`', ' ', trim($object->getName())));

        // Lookup existing IDs
        $existingIds = $this->findIdsByColumnList([
            'parent_id'   => $object->getParentId(),
            'parent_type' => $object->getParentType(),
            'name'        => $object->getName(),
        ]);

        // found something? assign that ID if so
        if ($existingIds) {
            $object->setId(array_shift($existingIds));
        }

        return true;
    }


    /**
     * Find all groups for a parent type.
     *
     * @param int|int[] $parentId   The parent ID (or list thereof) to find for
     * @param string    $parentType One of the `Group::PARENT_TYPE_*` constants
     * @param bool      $cache      If false, bypass result cache
     * @return GenericCollection|Group[]
     * @throws MapperException
     */
    public function findForParent($parentId, string $parentType, bool $cache = true): GenericCollection
    {
        return $this->findByColumnList(
            [
                'parent_id'   => $parentId,
                'parent_type' => $parentType,
            ],
            [
                'order' => ['name' => 'ASC'],
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
     * Find a group with the given name for the given parent.
     * If none is found, create one and return it.
     *
     * @param int    $parentId
     * @param string $parentType
     * @param string $name
     * @param bool   $cache
     * @return Group
     * @throws MapperException if for some reason it can't be created
     */
    public function findOrCreateOneForParentByName(
        int $parentId,
        string $parentType,
        string $name,
        bool $cache = true
    ): Group
    {
        try {
            return $this->findOneByColumnList(
                [
                    'parent_id'   => $parentId,
                    'parent_type' => $parentType,
                    'name'        => $name,
                ],
                [],
                ['cache' => $cache,]
            );

        } catch (MapperException\Deleted $e) {
            // It was deleted; restore it
            $item = $e->getDeletedObject();
            $this->restoreSoftDeleted($item);
            return $item;

        } catch (MapperException $e) {
            // Create one
            $item = $this->createModel()
                         ->setParentId($parentId)
                         ->setParentType($parentType)
                         ->setName($name);
            $this->save($item);
            return $item;
        }
    }


    /**
     * Find a group with the given name for the given folder.
     * If none is found, create one and return it.
     *
     * @param int    $folderId
     * @param string $name
     * @param bool   $cache
     * @return Group
     * @throws MapperException if for some reason it can't be created
     */
    public function findOrCreateOneForFolderByName(int $folderId, string $name, bool $cache = true): Group
    {
        return $this->findOrCreateOneForParentByName($folderId, Group::PARENT_TYPE_CATEGORY, $name, $cache);
    }


    /**
     * No notifications.
     *
     * @param object $object
     * @param string $verb
     * @param object $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null): bool
    {
        return true;
    }
}
