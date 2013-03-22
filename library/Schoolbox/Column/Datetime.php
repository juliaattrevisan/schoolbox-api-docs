<?php
/**
 * Datetime (no timezone) column type.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Column;
use \DateTime as DateObject;
use \Alaress_Date_Format as DateWrapper;

class Datetime implements IColumnType {
	protected $input_format  = 'Y-m-d H:i:s';
	protected $output_format = 'Y-m-d H:i:s';

	/**
	 * @param string $value
	 *
	 * @return \DateTime
	 */
	protected function getInputDate($value) {
		//
		// ! in front of format sets all fields back to Unix epoch until reset.
		return DateObject::createFromFormat('!'.$this->input_format, $value);
	}

	/**
	 * @param string $value
	 *
	 * @return \DateTime
	 */
	public function hydrate($value) {
		$input_date = $this->getInputDate($value);
		if ($input_date instanceof DateObject) {
			return new DateWrapper($input_date, $this->input_format);
		}
		return false;
	}

	/**
	 * @param \DateTime $value
	 *
	 * @return string
	 */
	public function dehydrate($value) {
		if (!($value instanceof DateObject)) {
			return null;
		}
		return $value->format($this->output_format);
	}
}