<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\My\Folder;

use Schoolbox\Collection;
use Schoolbox\Resource\AbstractFolderMapper;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\Resource\Folder\ILocator;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * MyFolder Mapper. Maps myFolder table to portfolio folder objects.
 *
 * @package Schoolbox\Resource\My\Folder
 * @method Model createModel($idValue = null)
 */
class Mapper extends AbstractFolderMapper implements ILocator
{
    /**
     * {@inheritdoc}
     * @return Model|object
     */
    public function findById($id)
    {
        // special case: 0 == root, but not in db
        if ($id == 0) {
            return $this->createModel()
                        ->setId(0)
                        ->setName('Top Level')
                        ->setParent($this->createModel()->setId(IFolder::ROOT_PARENT_ID));
        }

        return parent::findById($id);
    }

    /**
     * @return void
     */
    protected function mapping()
    {
        parent::mapping();

        $this->hasTable('myFolder');

        $this->hasModel(Model::class);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joins('owner', UserMapper::class, 'id');
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteContents']);
    }


    /**
     * Return the folders contained within the folder with the given ID
     *
     * @param int $nodeId
     * @return Collection|IFolder[]
     */
    public function findByParentId($nodeId)
    {
        return $this->findByColumnList([
            'parent' => $nodeId,
            'owner'  => $_SESSION['id'] // TODO: FIX. DO NOT MERGE THIS
        ]);
    }


    /**
     * Is $possibleDescendant a descendant of $possibleAncestor?
     *
     * For maximum robustness, we treat pseudo-root as child of '#'
     *
     * @param Model $possibleDescendant The folder that may be a descendant of $possibleAncestor
     * @param Model $possibleAncestor
     * @return bool
     * @throws \OverflowException If ancestry loop detected
     */
    public function isDescendant(Model $possibleDescendant, Model $possibleAncestor)
    {
        // Pseudo-root parent is never a descendant
        if (IFolder::ROOT_PARENT_ID === $possibleDescendant->getId()) {
            return false;
        }

        // Pseudo-root is only a descendant of its imaginary parent
        if (IFolder::ROOT_ID === (int)$possibleDescendant->getId()) {
            return IFolder::ROOT_PARENT_ID === $possibleAncestor->getId();
        }

        // Nodes do not descend from themselves
        if ((int)$possibleDescendant->getId() === (int)$possibleAncestor->getId()) {
            return false;
        }

        // All folders are descendant from pseudo-root (and its parent)
        if (IFolder::ROOT_ID === (int)$possibleAncestor->getId() || IFolder::ROOT_PARENT_ID === $possibleAncestor->getId()) {
            return true;
        }

        /** @var true[] $ancestry Loop detection, indexed by id */
        $ancestry = [];

        // Climb inheritance tree
        $parent                      = $this->findById($possibleDescendant->getParent()->getId());
        $ancestry[$parent->getId()] = true;
        while (IFolder::ROOT_ID !== (int)$parent->getId()) {
            if ((int)$parent->getId() === (int)$possibleAncestor->getId()) {
                return true;
            }
            $parent   = $this->findById($parent->getParent()->getId());
            $parentId = $parent->getId();
            // Have we see this parent before?
            if (array_key_exists($parentId, $ancestry)) {
                $loop = "{$possibleDescendant->getId()}," . implode(',', array_keys($ancestry));
                throw new \OverflowException(
                    "Ancestry loop detected. MyFolder '{$possibleDescendant->getId()}' is its own ancestor [$loop]"
                );
            }
            $ancestry[$parentId] = true;
        }
        return false;
    }

    /**
     * @param Model $folder
     * @return bool
     */
    public function softDeleteContents(Model $folder): bool
    {
        // delete direct folders in this folder
        $this->deleteByColumn('parent', $folder->getId());

        // delete direct files in this folder
        $myFileMapper = \Schoolbox\Resource\My\File\Mapper::getInstance();
        $myFileMapper->deleteByColumn('parent', $folder->getId());

        return true;
    }
}
