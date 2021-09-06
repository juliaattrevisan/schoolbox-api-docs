<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External;

use Schoolbox;

/**
 * Class TimetableCycleMapper
 *
 * Used to create cycles which we associate with campuses.
 * Cycles contain periods.
 *
 * @package Schoolbox\External
 * @method TimetableCycle createModel($idValue = null)
 * @method TimetableCycle findOneByColumn($columnName, $columnValue, array $options = [])
 */
class TimetableCycleMapper extends Schoolbox\Mapper
{

    /**
     * {@inheritdoc}
     */
    protected function mapping()
    {
        $this->hasTable('sis_timetable_cycle');
        $this->hasModel(TimetableCycle::class);
        $this->hasMap([
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true
        ]);
        $this->hasMap([
            'column'   => 'title',
            'property' => 'title',
            'type'     => self::TYPE_STRING,
        ]);
        $this->hasMap([
            'column'   => 'cycle_identifier',
            'property' => 'cycleIdentifier',
            'type'     => self::TYPE_STRING,
        ]);
        $this->hasMap([
            'column'   => 'cycle_length',
            'property' => 'cycleLength',
            'type'     => self::TYPE_INTEGER,
        ]);
        $this->hasMap([
            'column'   => 'day_length',
            'property' => 'dayLength',
            'type'     => self::TYPE_INTEGER,
        ]);
        $this->hasMap([
            'column'   => 'start',
            'property' => 'start',
            'type'     => self::TYPE_DATE,
        ]);
        $this->hasMap([
            'column'   => 'finish',
            'property' => 'finish',
            'type'     => self::TYPE_DATE,
        ]);
        $this->hasMap([
            'column'   => 'start_day',
            'property' => 'startDay',
            'type'     => self::TYPE_INTEGER,
        ]);
    }
}
