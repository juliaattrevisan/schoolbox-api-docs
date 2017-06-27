<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\MobileApp;

use Schoolbox\Resource\Storage;
use Schoolbox\Mapper;


class AppMapper extends Mapper
{
    /**
     * Setup method. Implementing classes to define their SQL map here.
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('mobile_app');

        $this->uses('app_id', self::TYPE_STRING, self::COLUMN_IDENTIFIER, 'id');
        $this->joins('pem_hash', Storage\Mapper::class, 'hash', null, null, 'pem', 'pem');
    }

}