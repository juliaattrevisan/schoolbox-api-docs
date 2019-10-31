<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource;

use Schoolbox;

/**
 * Class AbstractFolderMapper
 *
 * Provides common mapping functionality for AbstractFolders
 *
 * @package Schoolbox\Resource
 */
abstract class AbstractFolderMapper extends Schoolbox\Mapper
{
    public const COL_PARENT = 'parent';

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->joins('parent', static::class, 'id');
    }

}
