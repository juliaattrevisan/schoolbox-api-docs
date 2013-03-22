<?php
/**
 * Alaress_Date_Strategy_DateTime
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Alaress_Date_Strategy_DateTime extends Alaress_Date_Strategy_Abstract {

	/**
	 * @var DateTime
	 */
	protected $datetime;

	/**
	 * @var Alaress_Date_Locale_Interface
	 */
	protected $locale;

	public function setFromFormat($datetime, $format) {
		if ($datetime instanceof DateTime) {
			$this->datetime = $datetime;
		}
		else {
			$this->datetime = DateTime::createFromFormat($format, $datetime);
		}
	}

	public function setLocale($locale) {
		$this->locale = $locale;
		return $this;
	}

	public function getLocale() {
		return $this->locale;
	}

	public function getUsingFormat($format) {
		if ($this->isValid()) {
			return $this->datetime->format($format);
		}
		return null;
	}

	public function isValid() {
		return $this->datetime instanceof DateTime;
	}

}
