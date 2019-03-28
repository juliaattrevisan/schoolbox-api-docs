<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox;

class Campus extends BaseModel implements ValueLabelInterface {

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

    /**
     * Find a key/value representation of this model's data
     * and return it as a 2D array of value (an identifier)
     * and label (a human-readable description, usually)
     *
     * return [
     *     'value' => ...
     *     'label' => ...
     * ];
     *
     * @return array
     */
    public function findValueLabelPair()
    {
        return [
            'value' => $this->getId(),
            'label' => $this->getName()
        ];
    }
}

