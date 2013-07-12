<?php
/**
 * Timetable Colour Model
 *
 * Object for colours in use in timetable
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2012 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */
class Timetable_Colour extends Model {
	/**
	 * @var int
	 */
	public $timetable_colour_id;

	/**
	 * @var string
	 */
    public $hex_colour;

	/**
	 * @param  string $hex_colour
	 *
	 * @return $this
	 */
	public function setHexColour($hex_colour) {
		$this->hex_colour = $hex_colour;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getHexColour() {
		return $this->hex_colour;
	}

	/**
	 * @param  int $timetable_colour_id
	 *
	 * @return $this
	 */
	public function setTimetableColourId($timetable_colour_id) {
		$this->timetable_colour_id = $timetable_colour_id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getTimetableColourId() {
		return $this->timetable_colour_id;
	}

}