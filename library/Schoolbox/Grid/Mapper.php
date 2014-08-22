<?php
/**
 * Mapper for grid layouts in Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Grid;

use Schoolbox\Mapper as BaseMapper;

class Mapper extends BaseMapper
{
    public function mapping()
    {
        $this->hasTable('grid');
        $this->hasModel('Schoolbox\\Grid\\Model');
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );

        // Layout: will be a choice between grid and columns
        $this->hasMap(
            array(
                'column'   => 'layout_type',
                'property' => 'layoutType',
                'type'     => self::TYPE_INTEGER,
            )
        );

        // Number of columns when using column layout
        $this->hasMap(
            array(
                'column'   => 'column_count',
                'property' => 'columnCount',
                'type'     => self::TYPE_INTEGER,
            )
        );

        // Grid width: always px as far as i know
        $this->hasMap(
            array(
                'column'   => 'grid_width',
                'property' => 'gridWidth',
                'type'     => self::TYPE_INTEGER,
            )
        );

        // Aspect ratio for grid items
        $this->hasMap(
            array(
                'column'   => 'aspect_ratio_x',
                'property' => 'aspectRatioX',
                'type'     => self::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'aspect_ratio_y',
                'property' => 'aspectRatioY',
                'type'     => self::TYPE_INTEGER,
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

//        // Joins
//        $this->hasJoin(
//            'related_resource',
//            array(
//                'mapper'    => '\\Schoolbox\\ResourceBooking\\Item\\RelatedResource\\Mapper',
//                'this_key'  => 'id',
//                'other_key' => 'resource_id',
//                'multiple'  => true,
//            )
//        );
//        $this->hasJoin(
//            'role',
//            array(
//                'mapper'    => '\\Schoolbox\\ResourceBooking\\Item\\RolePermission\\Mapper',
//                'this_key'  => 'id',
//                'other_key' => 'resource_id',
//                'multiple'  => true,
//            )
//        );
//        $this->registerCallback('postSave', array($this, 'postSave'));
    }
}
