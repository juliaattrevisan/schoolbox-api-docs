<?php

/**
 * ActivityLogMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC\ActivityLog;

class Mapper extends \Schoolbox\Mapper
{

    protected function mapping()
    {
        $this->hasTable('eotc_activitylog');
        $this->hasModel(Log::class);

        $this->hasMap(array(
            'column' => 'id',
            'property' => 'id',
            'type' => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column' => 'actioner_id',
            'property' => 'actioner',
            'type' => self::TYPE_MODEL,
            'join' => 'actioner',
        ));
        $this->hasMap(array(
            'column' => 'metadata_id',
            'property' => 'metadataId',
            'type' => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column' => 'message',
            'property' => 'message',
            'type' => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'created_at',
            'property' => 'createdAt',
            'type'     => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
            'column'   => 'updated_at',
            'property' => 'updatedAt',
            'type'     => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
            'column'   => 'deleted_at',
            'property' => 'deletedAt',
            'type'     => self::TYPE_DATETIME,
        ));
        $this->hasJoin('actioner', array(
            'mapper'    => '\\Schoolbox\\UserMapper',
            'this_key'  => 'actioner_id',
            'other_key' => 'id',
        ));
    }
}
