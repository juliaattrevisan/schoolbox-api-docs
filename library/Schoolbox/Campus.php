<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */
namespace Schoolbox;

class Campus {

	/** @var string */
    private $name;

	/** @var int */
    private $id;

	/** @var string */
	private $extTimetableCode;

	/** @var int */
	private $timetableDay;


	/**
	 * @param int $id
	 * @return self
	 */
	public function setId($id) {
        $this->id = $id;
	    return $this;
    }


	/**
	 * @return int
	 */
	public function getId() {
        return $this->id;
    }


	/**
	 * @param string $name
	 * @return self
	 */
	public function setName($name) {
        $this->name = $name;
	    return $this;
    }


	/**
	 * @return string
	 */
	public function getName() {
        return $this->name;
    }


	/**
	 * @param string $extTimetableCode
	 * @return self
	 */
	public function setExtTimetableCode($extTimetableCode) {
		$this->extTimetableCode = $extTimetableCode;
		return $this;
	}


	/**
	 * @return string
	 */
	public function getExtTimetableCode() {
		return $this->extTimetableCode;
	}


	/**
	 * @param int $timetableDay
	 * @return self
	 */
	public function setTimetableDay($timetableDay) {
		$this->timetableDay = $timetableDay;
		return $this;
	}


	/**
	 * @return int
	 */
	public function getTimetableDay() {
		return $this->timetableDay;
	}


}

