<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\My\File;


use Schoolbox\Resource;
use Schoolbox\Resource\AbstractFileMapper;
use Schoolbox\Resource\File\IFileMapper;
use Schoolbox\Resource\File\ILocator;
use Schoolbox\Resource\Storage;

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

}