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

class Timetable_ColourMapper extends Schoolbox\Mapper {

	protected $table = 'lu_timetable_colour';

	protected function mapping() {
		$this->hasTable('lu_timetable_colour');
		$this->hasModel('Timetable_Colour');
		$this->hasMap(
			array(
				'column' => 'timetable_colour_id',
				'property' => 'timetableColourId',
				'type' => self::TYPE_INTEGER,
				'identifier' => true
			)
		);

		$this->hasMap(
			array(
				'column' => 'hex_colour',
				'property' => 'hexColour',
				'type' => self::TYPE_STRING,
			)
		);
	}

}