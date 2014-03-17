<?php
/**
 * Mapper for resource moderation types.
 *
 * This is a lookup table, so it doesn't really need a mapper, but here it is
 * anyway.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Item\RelatedResource;
use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper {

    public function mapping() {
        $this->hasTable('resource_relation');
        $this->hasModel('Schoolbox\\ResourceBooking\\Item\\RelatedResource\\Model');
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
                'column'   => 'extra_resource_id',
                'property' => 'relatedResource',
                'type'     => self::TYPE_MODEL,
                'join'     => 'related_resource',
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
            'related_resource',
            array(
                'mapper'    => '\\Schoolbox\\ResourceBooking\\Item\\Mapper',
                'this_key'  => 'extra_resource_id',
                'other_key' => 'id',
            )
        );
    }

}
