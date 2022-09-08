<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\Capability;

use PDOStatement;
use Schoolbox\Assessment\Rubric\DataModel\CapabilityUsage\Mapper as CapabilityUsageMapper;
use Schoolbox\Assessment\Rubric\Util;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Mapper for rubric capabilities.
 *
 * @package Schoolbox\Assessment\Rubric\DataModel
 * @method Model createModel($idValue = null)
 * @method Model findById($id)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[] findByColumnList($columnList, $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 */
class Mapper extends BaseMapper
{
    /* 1000 is the limit in the database, but we choose 900 as our limit due to concern about special characters
     * (eg. bullet points) requiring more bytes than normal characters.
     * @see expanded reason in www/static/js/submissions/jquery.rubric-editable.js.
     * @see also SA-13689.
     */
    public const CAPABILITY_NAME_MAX_CHARS_TO_STORE = 900;

    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable('rubric_capability');
        $this->hasModel(Model::class);

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
            'curriculum_node_for_rubric_capability',
            NodeMapper::class,
            'id',
            'curriculum_node_id',
            'rubric_capability_id',
            null,
            null,
            null,
            'curriculumNodes'
        );

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'verifyUniqueness']);

//        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteIndicators']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteUsages']);
    }


    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        /** @var NodeMapper $curriculumNodeMapper */
        $curriculumNodeMapper = $this->getJoinMapper('curriculum_node_for_rubric_capability');
        $collection           = $this->createCollection();
        foreach ($statement as $row) {
            /** @var Model $capability */
            $capability                       = $this->hydrate($row);
            $collection[$capability->getId()] = $capability;
        }

        // Fetch curriculum nodes separately
        $capabilityNodes = $curriculumNodeMapper->findForOtherMapper(
            $this,
            'curriculum_node_for_rubric_capability',
            $collection->keys()
        );
        foreach ($collection as $capability) {
            if (isset($capabilityNodes[$capability->getId()])) {
                $capability->setCurriculumNodes($capabilityNodes[$capability->getId()]);
            } else {
                $capability->setCurriculumNodes($curriculumNodeMapper->createCollection());
            }
        }

        return $collection;
    }


    /**
     * Pre-save callback.
     *
     * If a capability with the same name already exists within its parent,
     * then block saving.
     *
     * This replaces the unique index on this table, which had to be removed
     * because MySQL max index size doesn't allow more than about ~330 UTF-8
     * characters.
     *
     * @param Model $object
     * @return bool  Always true
     * @throws \Schoolbox\Mapper\Exception
     * @throws MapperException if the name isn't unique
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function verifyUniqueness(Model $object): bool
    {
        $pb = $this->getPredicateBuilder()
                   ->equals('parent_id', $object->getParentId())
                   ->equals('parent_type', $object->getParentType());

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
                    sprintf('Rubric capability name "%s" is already in use in this folder', $existingName)
                );
            }
        }

        return true;
    }


    /**
     * Post-delete callback for this capability.
     *
     * Soft-deleting a capability (hard) deletes all of its usages.
     *
     * @param Model $capability
     */
    public function deleteUsages(Model $capability): void
    {
        CapabilityUsageMapper::getInstance()->deleteByColumn('rubric_capability_id', $capability->getId());
    }


    /**
     * Find all capabilities for a parent type.
     *
     * @param int|int[] $parentId   The parent ID (or list thereof) to find for
     * @param string    $parentType One of the `Model::PARENT_TYPE_*` constants
     * @param bool      $cache      If false, bypass result cache
     * @return GenericCollection|Model[]
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
     * Find all capabilities for a folder.
     *
     * @param int|int[] $folderId The folder ID (or list thereof) to find for
     * @param bool      $cache    If false, bypass result cache
     * @return GenericCollection|Model[]
     * @throws MapperException
     */
    public function findForFolder($folderId, bool $cache = true): GenericCollection
    {
        return $this->findForParent($folderId, Model::PARENT_TYPE_CATEGORY, $cache);
    }


    /**
     * Find a capability, given its parent ID, type and name.
     *
     * @param int    $parentId   The parent ID to find for
     * @param string $parentType One of the `Model::PARENT_TYPE_*` constants
     * @param string $name       The name of the indicator
     * @param bool   $cache      If false, bypass result cache
     * @return Model|null
     */
    public function findOneForParentByName(
        int $parentId,
        string $parentType,
        string $name,
        bool $cache = true
    ): ?Model {
        $capabilities   = $this->findByColumnList(
            [
                'parent_id'   => $parentId,
                'parent_type' => $parentType,
            ],
            ['cache' => $cache,]
        );
        $normalizedName = Util::normalizeName($name);
        foreach ($capabilities as $capability) {
            /** @var Model $capability */
            if ($normalizedName === Util::normalizeName($capability->getName())) {
                return $capability;
            }
        }
        return null;
    }


    /**
     * Find a capability, given its folder and name.
     *
     * @param int    $folderId  The folder ID to find for
     * @param string $name      The name of the capability
     * @param bool   $cache     If false, bypass result cache
     * @return Model|null
     */
    public function findOneForFolderByName(int $folderId, string $name, bool $cache = true): ?Model
    {
        return $this->findOneForParentByName($folderId, Model::PARENT_TYPE_CATEGORY, $name, $cache);
    }


    /**
     * Find a capability with the given name for the given parent.
     * If none is found, create one and return it.
     *
     * Joins hydrated:
     *  * Curriculum nodes
     *
     * @param int                    $parentId
     * @param string                 $parentType
     * @param string                 $name
     * @param bool                   $cache
     * @param Collection|null $curriculumNodes
     * @return Model
     * @throws MapperException if for some reason it can't be created
     */
    public function findOrCreateOneForParentByName(
        int $parentId,
        string $parentType,
        string $name,
        bool $cache = true,
        ?Collection $curriculumNodes = null
    ): Model {
        // Try to find a capability with this name
        $existing = $this->findOneForParentByName($parentId, $parentType, $name, $cache);
        if ($existing) {
            // Found
            return $existing;
        }

        // If curriculum nodes are not provided, just set empty collection.
        if (null === $curriculumNodes) {
            /** @var NodeMapper $nodeMapper */
            $nodeMapper      = $this->getJoinMapper('curriculum_node_for_rubric_capability');
            $curriculumNodes = $nodeMapper->createCollection();
        }

        // Couldn't find. Create one
        $item = $this->createModel()
                     ->setParentId($parentId)
                     ->setParentType($parentType)
                     ->setName($name)
                     ->setCurriculumNodes($curriculumNodes);
        $this->save($item);
        return $item;
    }


    /**
     * Find a capability with the given name for the given folder.
     * If none is found, create one and return it.
     *
     * Joins hydrated:
     *  * curriculum nodes
     *
     * @param int    $folderId
     * @param string $name
     * @param bool   $cache
     * @return Model
     * @throws MapperException if for some reason it can't be created
     */
    public function findOrCreateOneForFolderByName(int $folderId, string $name, bool $cache = true): Model
    {
        return $this->findOrCreateOneForParentByName($folderId, Model::PARENT_TYPE_CATEGORY, $name, $cache);
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
