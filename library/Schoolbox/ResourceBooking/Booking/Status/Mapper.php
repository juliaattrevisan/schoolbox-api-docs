<?php

/**
 * Mapper for resource moderation types.
 *
 * This is a lookup table, so it doesn't really need a mapper, but here it is
 * anyway.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking\Status;

use Schoolbox\Mapper as BaseMapper;

/**
 * @package Schoolbox\ResourceBooking\Booking\Status
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{

    public function mapping()
    {
        $this->hasTable('lu_resource_booking_status');
        $this->hasModel('Schoolbox\\ResourceBooking\\Booking\\Status\\Model');
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
                'column'   => 'name',
                'property' => 'name',
                'type'     => self::TYPE_STRING,
            )
        );
    }
}
