<?php

/**
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder\Visits;

use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\RoleMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('homepage_visits');
        $this->hasModel(Model::class);
        $this->uses('date', self::TYPE_DATE);
        $this->uses('hitcount', self::TYPE_INTEGER);
        $this->joins('user_group_id', RoleMapper::class, 'id', null, null, 'role', 'role');
        $this->joins('category_id', FolderMapper::class, 'id', null, null, 'folder', 'folder');
    }
}
