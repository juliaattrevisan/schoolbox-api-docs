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
    private $name;
    private $id;
	private $extTimetableCode;
	private $timetableDay;

    public function setId($id) {
        $this->id = $id;
    }

    public function getId() {
        return $this->id;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

	public function setExtTimetableCode($extTimetableCode) {
		$this->extTimetableCode = $extTimetableCode;
	}

	public function getExtTimetableCode() {
		return $this->extTimetableCode;
	}

	public function setTimetableDay($timetableDay) {
		$this->timetableDay = $timetableDay;
	}

	public function getTimetableDay() {
		return $this->timetableDay;
	}


}

