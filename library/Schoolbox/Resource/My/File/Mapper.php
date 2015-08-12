<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\My\File;


use Schoolbox\Collection;
use Schoolbox\Resource;
use Schoolbox\Resource\AbstractFileMapper;
use Schoolbox\Resource\File\IFileMapper;
use Schoolbox\Resource\File\ILocator;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

/**
 * Class Mapper
 *
 * MyFile Mapper. Maps myFile table to portfolio File objects
 *
 * @package Schoolbox\Resource\My\File
 * @method Model findById($id)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends AbstractFileMapper implements ILocator, IFileMapper
{

    /**
     * @return string
     */
    protected function getFolderFieldname()
    {
        return 'parent';
    }


    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        parent::mapping();

        $model = new Model();
        $model->setStorage($this->getStorageMapper()->createModel());

        $this->hasTable('myFile');
        $this->hasPrototype($model);

        $this->uses('published', self::TYPE_DATETIME, null, 'publishedAt');

        $this->joins($this->getFolderFieldname(), Resource\My\Folder\Mapper::class, 'id', null, null, 'folder');
    }


    /**
     * Return the files contained within the folder with the given ID
     *
     * @param int $nodeId
     * @return Collection|Model[]
     */
    public function findByFolderId($nodeId)
    {
        return $this->findByColumnList(
            array(
                'parent' => $nodeId,
                'owner'  => $this->getSessionUserId()
            ),
            ['order' => ['description' => 'asc',],]
        );
    }


    /**
     * @return int
     */
    protected function getSessionUserId()
    {
        return $this->sessionData['user'] instanceof User
            ? $this->sessionData['user']->getId()
            : null;
    }

    /**
     * Find all image files owned by a particular user
     *
     * @param $userId
     * @return Collection
     */
    public function findAllImagesByUser($userId)
    {
        return $this->findByColumn('owner', $userId, [ 'mime' => 'image/*' ]);
    }
}