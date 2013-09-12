<?php
/**
 * Mapper for class objects.
 *
 * This is only used by the Schoolbox connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External;
use Schoolbox;

class TimetableCampusMapper extends Schoolbox\External\Mapper {

    /**
     * {@inheritdoc}
     */
    protected function mapping() {
        $this->hasTable('timetable_campus');
        $this->hasModel('\\Schoolbox\\External\\TimetableCampus');
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true
        ));
        $this->hasMap(array(
            'column'   => 'cycle_id',
            'property' => 'cycle',
            'type'     => self::TYPE_MODEL,
            'join'     => 'cycle'
        ));
        $this->hasMap(array(
            'column'   => 'campus_id',
            'property' => 'campus',
            'type'     => self::TYPE_MODEL,
            'join'     => 'campus'
        ));
        $this->hasMap(array(
            'column'   => 'start',
            'property' => 'start',
            'type'     => self::TYPE_DATE,
        ));
        $this->hasMap(array(
            'column'   => 'reset',
            'property' => 'reset',
            'type'     => self::TYPE_DATE,
        ));
        $this->hasMap(array(
            'column'   => 'finish',
            'property' => 'finish',
            'type'     => self::TYPE_DATE,
        ));
        $this->hasMap(array(
            'column'   => 'offset',
            'property' => 'offset',
            'type'     => self::TYPE_INTEGER,
        ));
        $this->hasJoin(
            'cycle',
            array(
                'mapper'    => '\\Schoolbox\\External\\TimetableCycleMapper',
                'this_key'  => 'cycle_id',
                'other_key' => 'id',
            )
        );
        $this->hasJoin(
            'campus',
            array(
                'mapper'    => '\\Schoolbox\\CampusMapper',
                'this_key'  => 'campus_id',
                'other_key' => 'id',
            )
        );
    }
}
