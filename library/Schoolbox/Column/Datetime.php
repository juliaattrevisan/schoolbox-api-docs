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
	 * @param string            $value
	 * @param \Schoolbox\Mapper $mapper
	 *
	 * @return \DateWrapper
	 */
	public function hydrate($value, $mapper = null) {
		$input_date = $this->getInputDate($value);
		if ($input_date instanceof DateObject) {
			return new DateWrapper($input_date, $this->input_format);
		}
		return false;
	}

	/**
	 * Datetime mapper can dehydrate either a DateTime object, an
	 * Alaress_Date_Format object or a string (used to create DateTime object,
	 * then formatted).
	 *
	 * @param mixed             $value
	 * @param \Schoolbox\Mapper $mapper
	 *
	 * @return string
	 */
	public function dehydrate($value, $mapper = null) {
		if ($value instanceof DateWrapper) {
			return $value->getUsingFormat($this->output_format);
		}
		if (is_string($value)) {
			try {
				$value = new DateObject($value);
			}
			catch (\Exception $e) {
			}
		}
		if ($value instanceof DateObject) {
			return $value->format($this->output_format);
		}
		return null;
	}
}