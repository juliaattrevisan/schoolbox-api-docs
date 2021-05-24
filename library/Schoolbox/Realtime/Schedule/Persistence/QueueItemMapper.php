<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Persistence;

use Schoolbox\Mapper;

class QueueItemMapper extends Mapper
{

    protected function mapping()
    {
        $this->hasTable('scheduleq');
        $this->hasModel(QueueItem::class);

        // note that this is a dynamic property that does not exist in the model (but it does in the DB)
        $this->hasMap(array(
            'column'     => 'id_hash',
            'property'   => 'hash',
            'type'       => self::TYPE_STRING,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'identifier',
            'property'   => 'identifier',
            'type'       => self::TYPE_SERIALIZED,
        ));
        $this->hasMap(array(
            'column'     => 'command',
            'property'   => 'command',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'data',
            'property'   => 'data',
            'type'       => self::TYPE_SERIALIZED,
        ));
    }


    /**
     * This mapper does not raise notifications.
     *
     * @param object $object
     * @param string $verb
     * @param null   $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return false;
    }
}
