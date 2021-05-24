<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Permission\SiteAccess;

/**
 * Class Mapper
 * @package Schoolbox\API\Help\Token
 *
 * @method Model findById($id)
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * Setup method. Implementing classes to define their SQL map here.
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('site_access');
        $this->hasModel(Model::class);

        $this->uses('site_school_code', self::TYPE_STRING, self::COLUMN_IDENTIFIER);
        $this->uses('access_token', self::TYPE_STRING);
    }
}
