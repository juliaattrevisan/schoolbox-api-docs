<?php

/**
 * A mapping for the join between resources and their moderators.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Item\Moderator;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{

    public function mapping()
    {
        $this->hasTable('resource_moderator');
        $this->hasModel('Schoolbox\\ResourceBooking\\Item\\Moderator\\Model');
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'resource_id',
                'property' => 'resource',
                'type'     => self::TYPE_MODEL,
                'join'     => 'resource',
            )
        );
        $this->hasMap(
            array(
                'column'   => 'moderator_id',
                'property' => 'moderator',
                'type'     => self::TYPE_MODEL,
                'join'     => 'moderator',
            )
        );
        $this->hasJoin(
            'resource',
            array(
                'mapper'    => '\\Schoolbox\\ResourceBooking\\Item\\Mapper',
                'this_key'  => 'resource_id',
                'other_key' => 'id',
            )
        );
        $this->hasJoin(
            'moderator',
            array(
                'mapper'    => '\\Schoolbox\\UserMapper',
                'this_key'  => 'moderator_id',
                'other_key' => 'id',
            )
        );
    }
}
