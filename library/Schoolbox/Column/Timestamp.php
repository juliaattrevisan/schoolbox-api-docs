<?php
/**
 * Timestamp column type - the same thing as the datetime column type, but uses
 * Unix timestamps.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Column;
use \DateTime as DateObject;

class Timestamp extends Datetime implements IColumnType {
	protected $input_format  = 'U';
	protected $output_format = 'U';

	/**
	 * @param string $value
	 *
	 * @return \DateTime
	 */
	public function hydrate($value) {
		if (!is_numeric($value)) {
			return false;
		}
		$date = new DateObject();
		$date->setTimestamp($value);
		return $date;
	}
}