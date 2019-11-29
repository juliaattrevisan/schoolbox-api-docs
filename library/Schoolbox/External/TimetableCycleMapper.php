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

class TimetableCycleMapper extends Schoolbox\Mapper {

    /**
     * {@inheritdoc}
     */
    protected function mapping() {
        $this->hasTable('sis_timetable_cycle');
        $this->hasModel(TimetableCycle::class);
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true
        ));
        $this->hasMap(array(
            'column'   => 'title',
            'property' => 'title',
            'type'     => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'   => 'cycle_length',
            'property' => 'cycleLength',
            'type'     => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'   => 'day_length',
            'property' => 'dayLength',
            'type'     => self::TYPE_INTEGER,
        ));
        $this->hasMap(array(
            'column'   => 'regular',
            'property' => 'regular',
            'type'     => self::TYPE_BOOLEAN,
        ));
    }
}
