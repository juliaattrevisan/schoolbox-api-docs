<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Dropbox;

use Schoolbox;
use Schoolbox\Resource;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * TODO consider refactoring this into the Resource\AbstractFile abstraction once merged with SB-7185
 *
 * @package Schoolbox\Dropbox
 * @method  Model findById($id)
 * @method  Model createModel()
 */
class Mapper extends Schoolbox\Mapper
{

    /** @var Resource\Storage\Mapper */
    protected $storageMapper;


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
        $model = new Model();
        $model->setStorage($this->getStorageMapper()->createModel());

        $this->hasTable('dropbox');
        $this->hasPrototype($model);

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
            SELECT ci.id
            FROM component_instance ci
            JOIN component_definition cd ON ci.`component_definition_id`=cd.`id`
            WHERE class = 'Component_Homepage_DropBoxController'
            AND ci.module = 'homepage'
            AND identifier = ?
        ", [ $folder->getId() ])->fetchColumn() == $folder->getId();
    }

} 