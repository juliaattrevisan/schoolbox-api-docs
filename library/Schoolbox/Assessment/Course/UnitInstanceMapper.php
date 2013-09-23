<?php
/**
 * Mapper for the unit instance model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Schoolbox\Mapper;

class UnitInstanceMapper extends Mapper {

    protected function mapping() {
        $this->hasTable('course_unit_instance');
        $this->hasModel('\\Schoolbox\\Assessment\\Course\\UnitInstance');
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        // Unit category: the category that this unit was created from, not the
        // one that the component is on
        $this->hasMap(array(
            'column'     => 'unit_category_id',
            'property'   => 'unitCategory',
            'type'       => self::TYPE_MODEL,
            'join'       => 'unit_category',
        ));
        // Component: the component that this unit is displayed on
        $this->hasMap(array(
            'column'     => 'component_id',
            'property'   => 'component',
            'type'       => self::TYPE_MODEL,
            'join'       => 'component',
        ));
        $this->hasMap(array(
            'column'     => 'title',
            'property'   => 'title',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'open_date',
            'property'   => 'openDate',
            'type'       => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
            'column'     => 'due_date',
            'property'   => 'dueDate',
            'type'       => self::TYPE_DATETIME,
        ));
        $this->hasMap(array(
            'column'     => 'created_at',
            'property'   => 'created',
            'type'       => self::TYPE_DATETIME,
            'created_timestamp' => true,
        ));
        $this->hasMap(array(
            'column'     => 'deleted_at',
            'property'   => 'deleted',
            'type'       => self::TYPE_DATETIME,
        ));

        $this->hasJoin('unit_category', array(
            'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
            'this_key'  => 'unit_category_id',
            'other_key' => 'id',
        ));
        $this->hasJoin('component', array(
            'mapper'    => 'Component_InstanceMapper',
            'this_key'  => 'component_id',
            'other_key' => 'id',
        ));

        $this->hasSoftDeleteDateField('deleted_at');
    }
}
