<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox;

/**
 * Mapper for rubric groups.
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 * @method Group createModel($idValue = null)
 * @method Group findById($id)
 * @method Group[] findByColumnList($columnList, $options = [])
 * @method Group findOneByColumnList($columnList, $order = [], $options = [])
 */
class GroupMapper extends Schoolbox\Mapper
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
    }


    /**
     * Find a group with the given name for the given parent.
     * If none is found, create one and return it.
     *
     * @param int    $parentId
     * @param string $parentType
     * @param string $name
     * @return Group
     * @throws Schoolbox\Mapper\Exception if for some reason it can't be created
     */
    public function findOrCreateOneForParentByName(int $parentId, string $parentType, string $name): Group
    {
        try {
            return $this->findOneByColumnList([
                'parent_id'   => $parentId,
                'parent_type' => $parentType,
                'name'        => $name,
            ]);
        } catch (Schoolbox\Mapper\Exception $e) {
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
     * @return Group
     * @throws Schoolbox\Mapper\Exception if for some reason it can't be created
     */
    public function findOrCreateOneForFolderByName(int $folderId, string $name): Group
    {
        return $this->findOrCreateOneForParentByName($folderId, Group::PARENT_TYPE_CATEGORY, $name);
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
