<?php
/**
 * Stock-standard way of doing it, for backwards compatibility when not using
 * PHP 5.3 which has that wacky DateTime object.
 *
 * Uses the Alaress_Date_Interface to enforce method definition
 */
class Alaress_Date_V52 extends Alaress_Date_Abstract implements Alaress_Date_Interface {

	/**
	 * Transform the datetime into a unix timestamp
	 *
	 * @return string
	 */
	public function getUnixTimeStamp() {
		return $this->getUsingFormat('U');
	}

	/**
	 * Transform the datetime into an Americanised version
	 *
	 * @return string
	 */
	public function getAmerican() {
		return $this->getUsingFormat('m-d-Y H:i:s');
	}

	/**
	 * Transform the datetime into the format specified in the config table
	 * Do some error checking
	 *
	 * @return string
	 */
	public function getDatabase() {
		$result = $this->getUsingFormat('Y-m-d H:i:s');
		//$result = $this->getUsingFormat(Cfg::get('date_database'));

		$illegal = array(
			'0000-00-00 00:00:00',
			'1970-01-01 10:00:00',
			'-0001-11-30 00:00:00',
		);

		if (in_array($result, $illegal) || strstr($result, '0000')) {
			return '';
		}

		return $result;
	}

	/**
	 * Transform the datetime into an arbitrary format
	 *
	 * @param  $format
	 * @return string
	 */
	public function getUsingFormat($format) {
		return date($format, strtotime($this->getDateTime()));
	}
}
