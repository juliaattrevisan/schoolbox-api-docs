<?php
/**
 * Mapper for class objects.
 *
 * This is only used by the Schoolbox connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External;
use Schoolbox;

class TimetablePeriodMapper extends Schoolbox\Mapper {

    /**
     * {@inheritdoc}
     */
    protected function mapping() {
        $this->hasTable('sis_timetable_period');
        $this->hasModel(TimetablePeriod::class);
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
            'column'   => 'title',
            'property' => 'title',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'day',
            'property' => 'day',
            'type'     => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'   => 'sequence',
            'property' => 'sequence',
            'type'     => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'   => 'start',
            'property' => 'start',
            'type'     => self::TYPE_TIME,
        ));
        $this->hasMap(array(
            'column'   => 'finish',
            'property' => 'finish',
            'type'     => self::TYPE_TIME,
        ));
        $this->hasMap(array(
            'column'   => 'usable',
            'property' => 'usable',
            'type'     => self::TYPE_BOOLEAN,
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
            'class',
            array(
                'mapper'    => '\\Schoolbox\\External\\ClassEntryMapper',
                'this_key'  => 'class_id',
                'other_key' => 'id',
            )
        );
    }
}
