<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\File;

use Schoolbox\Resource;

/**
 * Class Mapper
 *
 * File Mapper. Maps file table to SBFS file objects.
 *
 * @package Schoolbox\Resource\File
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model findById($id)
 * @method Model createModel()
 */
class Mapper extends Resource\AbstractFileMapper implements ILocator, IFileMapper
{

    /**
     * @return string
     */
    protected function getFolderFieldname()
    {
        return 'category';
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

        $this->hasTable('file');
        $this->hasPrototype($model);

        $this->uses('comment', self::TYPE_STRING, null, 'description'); // TODO rename that field!
        $this->uses('hidden', self::TYPE_BOOLEAN);
        $this->uses('modified', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP, 'modifiedAt');

        $this->joins($this->getFolderFieldname(), Resource\Folder\Mapper::class, 'id', null, null, 'folder');
    }

}