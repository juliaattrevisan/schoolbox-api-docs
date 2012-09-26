<?php

/**
 * Timetable Colour Data Mapper
 *
 * Defines the mapping for the timetable colour lookup table
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2012 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

class Timetable_ColourMapper extends Mapper {

    protected $table = 'lu_timetable_colour';

    protected function mapping() {
        $this->hasMap(
            array(
                'column' => 'timetable_colour_id',
                'property' => 'timetable_colour_id',
                'type' => Mapper::TYPE_INTEGER,
                'identifier' => true
            )
        );

        $this->hasMap(
            array(
                'column' => 'hex_colour',
                'property' => 'hex_colour',
                'type' => Mapper::TYPE_STRING,
            )
        );

        parent::mapping();
    }

}