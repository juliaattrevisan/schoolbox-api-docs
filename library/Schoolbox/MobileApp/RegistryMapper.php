<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\MobileApp;


use Schoolbox\Mapper;
use Schoolbox\UserMapper;

class RegistryMapper extends Mapper
{

    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('mobile_app_registry');

        $this->hasModel(Registry::class);

        $this->joins('user_id', UserMapper::class);

        $this->uses('app_token',  self::TYPE_STRING, null, 'token');
        $this->uses('device_id',  self::TYPE_STRING);
        $this->uses('provider',   self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('touched_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
    }


    /**
     * @param int    $userId
     * @param string $token
     * @param string $provider
     * @return Registry
     * @throws Mapper\Exception
     */
    public function findById($userId, $token = null, $provider = null)
    {
        return $this->findOneByColumnList(array(
            'user_id'   => $userId,
            'app_token' => $token,
            'provider'  => $provider,
        ));
    }

}
