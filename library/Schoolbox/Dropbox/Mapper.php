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

class Mapper extends Schoolbox\Mapper
{

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('description', self::TYPE_STRING, null, 'title'); // TODO rename this field!
        $this->uses('realname', self::TYPE_STRING, null, 'filename');
        $this->uses('submitted', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP, 'createdAt');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joins('owner', UserMapper::class, 'id');
        $this->joins('category', Resource\Folder\Mapper::class, 'id', null, null, 'folder');
        $this->joins('hash', Resource\Storage\Mapper::class, 'hash', null, null, 'storage');
    }

} 