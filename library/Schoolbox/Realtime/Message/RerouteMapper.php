<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Message;

use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;

class RerouteMapper extends Mapper
{

    protected function mapping()
    {
        $this->hasTable('msg_reroute');
        $this->hasModel(Reroute::class);

        $this->hasMap(array(
            'column'     => 'user_id',
            'property'   => 'user',
            'type'       => self::TYPE_MODEL,
            'identifier' => true,
            'join'       => 'user'
        ));
        $this->hasMap(array(
            'column'     => 'msg_source_id',
            'property'   => 'source',
            'type'       => self::TYPE_MODEL,
            'identifier' => true,
            'join'       => 'source'
        ));
        $this->uses('delivery', self::TYPE_SET);
        $this->hasJoin('source', array(
            'mapper'     => '\\Schoolbox\\Realtime\\Message\\SourceMapper',
            'this_key'   => 'msg_source_id',
            'other_key'  => 'id'
        ));
        $this->hasJoin('user', array(
            'mapper'     => '\\Schoolbox\\UserMapper',
            'this_key'   => 'user_id',
            'other_key'  => 'id'
        ));
    }


    /**
     * @param int $userId
     * @param int $msgSrcId default 0 to retain interface compatibility
     * @return Reroute
     */
    public function findById($userId, $msgSrcId = 0)
    {
        return parent::findOneByColumnList(array(
            'user_id'        => $userId,
            'msg_source_id'  => $msgSrcId,
        ));
    }
}
