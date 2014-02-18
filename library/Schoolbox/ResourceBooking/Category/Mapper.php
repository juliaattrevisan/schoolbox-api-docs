<?php
/**
 * A mapper for resource booking categories.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 

namespace Schoolbox\ResourceBooking\Category;
use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper {

    public function mapping() {
        $this->hasTable('resource_category');
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
        $this->hasMap(
            array(
                'column'   => 'created_at',
                'property' => 'createdAt',
                'type'     => self::TYPE_DATETIME,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'modified_at',
                'property' => 'modifiedAt',
                'type'     => self::TYPE_DATETIME,
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
    }

}
