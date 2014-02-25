<?php
/**
 * A mapper for bookable resources.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 

namespace Schoolbox\ResourceBooking\Item;
use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper {

    public function mapping() {
        $this->hasTable('resource');
        $this->hasModel('Schoolbox\\ResourceBooking\\Item\\Model');
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
                'column'   => 'resource_category_id',
                'property' => 'category',
                'type'     => self::TYPE_MODEL,
                'join'     => 'category',
            )
        );
        $this->hasMap(
            array(
                'column'   => 'resource_moderation_type_id',
                'property' => 'moderationType',
                'type'     => self::TYPE_MODEL,
                'join'     => 'moderation_type',
            )
        );
        $this->hasMap(
            array(
                'column'   => 'name',
                'property' => 'name',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'description',
                'property' => 'description',
                'type'     => self::TYPE_STRING,
            )
        );

        // Minimum lead time, min and max reservation time
        $this->hasMap(
            array(
                'column'   => 'min_lead_time',
                'property' => 'minLeadTime',
                'type'     => self::TYPE_TIMESPAN,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'min_reservation_time',
                'property' => 'minReservationTime',
                'type'     => self::TYPE_TIMESPAN,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'max_reservation_time',
                'property' => 'maxReservationTime',
                'type'     => self::TYPE_TIMESPAN,
            )
        );

        // Created/Modified/Deleted
        $this->hasMap(
            array(
                'column'            => 'created_at',
                'property'          => 'createdAt',
                'type'              => self::TYPE_DATETIME,
                'created_timestamp' => true,
            )
        );
        $this->hasMap(
            array(
                'column'            => 'modified_at',
                'property'          => 'modifiedAt',
                'type'              => self::TYPE_DATETIME,
                'updated_timestamp' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'deleted_at',
                'property' => 'deletedAt',
                'type'     => self::TYPE_DATETIME,
            )
        );
        $this->hasSoftDeleteDateField('deleted_at');

        // Joins
        $this->hasJoin(
            'category',
            array(
                'mapper'    => '\\Schoolbox\\ResourceBooking\\Category\\Mapper',
                'this_key'  => 'resource_category_id',
                'other_key' => 'id',
            )
        );
        $this->hasJoin(
            'moderation_type',
            array(
                'mapper'    => '\\Schoolbox\\ResourceBooking\\Item\\ModerationType\\Mapper',
                'this_key'  => 'resource_moderation_type_id',
                'other_key' => 'id',
            )
        );
    }

}
