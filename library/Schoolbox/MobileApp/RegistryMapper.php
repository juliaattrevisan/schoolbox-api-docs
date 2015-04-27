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

class RegistryMapper extends Mapper
{

    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('mobile_app_registry');

        $this->hasModel('Schoolbox\\MobileApp\\Registry');

        $this->hasMap(array(
            'column'            => 'user_id',
            'property'          => 'user',
            'type'              => self::TYPE_MODEL,
            'join'              => 'user',
            'join_def'          => array(
                'other_key'         => 'id',
                'mapper'            => 'Schoolbox\\UserMapper'
            )
        ));
        $this->hasMap(array(
            'column'            => 'app_token',
            'property'          => 'token',
            'type'              => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'            => 'provider',
            'property'          => 'provider',
            'type'              => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'            => 'created_at',
            'property'          => 'createdAt',
            'type'              => self::TYPE_DATETIME,
            'created_timestamp' => true
        ));
        $this->hasMap(array(
            'column'            => 'touched_at',
            'property'          => 'touchedAt',
            'type'              => self::TYPE_DATETIME,
            'updated_timestamp' => true
        ));

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