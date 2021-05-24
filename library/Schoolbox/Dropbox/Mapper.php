<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Dropbox;

use PDOStatement;
use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\Resource;
use Schoolbox\UserMapper;
use Schoolbox\Resource\AbstractFileMapper;

/**
 * Class Mapper
 *
 * TODO consider refactoring this into the Resource\AbstractFile abstraction once merged with SB-7185
 *
 * @package Schoolbox\Dropbox
 * @method  Model findById($id)
 * @method  Model createModel()
 */
class Mapper extends AbstractFileMapper implements Schoolbox\IHomepageCleanable
{

    /** @var Resource\Storage\Mapper */
    protected $storageMapper;


    public function getFolderFieldname()
    {
        return 'category';
    }

    /**
     * @return Resource\Storage\Mapper
     */
    public function getStorageMapper()
    {
        return $this->storageMapper;
    }


    /**
     * @param Resource\Storage\Mapper $storageMapper
     * @return self
     */
    public function setStorageMapper($storageMapper)
    {
        $this->storageMapper = $storageMapper;
        return $this;
    }


    /**
     * @return void
     */
    public function setup()
    {
        $this->setStorageMapper(Resource\Storage\Mapper::getInstance());
    }


    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('dropbox');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('description', self::TYPE_STRING, null, 'title'); // TODO rename this field!
        $this->uses('realname', self::TYPE_STRING, null, 'filename');
        $this->uses('submitted', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP, 'createdAt');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joins('owner', UserMapper::class, 'id');
        $this->joins('category', Resource\Folder\Mapper::class, 'id', null, null, 'folder');
        $this->joins('hash', Resource\Storage\Mapper::class, 'hash', null, null, 'storage');
    }


    /**
     * @param Resource\Folder\Model $folder
     * @return bool
     */
    public function folderHasDropbox(Resource\Folder\Model $folder)
    {
        return $this->getDatabase()->query("
            SELECT COUNT(*)
            FROM component_instance ci
            JOIN component_definition cd ON ci.`component_definition_id`=cd.`id`
            WHERE class = 'Component_Homepage_DropBoxController'
            AND ci.module = 'homepage'
            AND identifier = ?
        ", [ $folder->getId() ])->fetchColumn() > 0;
    }

    /**
     * Soft-deletes content for specified homepage
     *
     * @param int $cid
     * @return void
     */
    public function homepageClean($cid)
    {
        $this->deleteByColumn('category', $cid);
    }
}
