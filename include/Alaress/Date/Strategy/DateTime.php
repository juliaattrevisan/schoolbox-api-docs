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

	public function getUnixTimestamp() {
		return $this->datetime->getTimestamp();
	}

	public function getDatabase() {
		return $this->datetime->format($this->getLocale()->databaseFormat());
	}

	public function getLong() {
		return $this->datetime->format($this->getLocale()->longFormat());
	}

	public function getShort() {
		return $this->datetime->format($this->getLocale()->shortFormat());
	}

	public function getDateOnly() {
		return $this->datetime->format($this->getLocale()->dateOnlyFormat());
	}

	public function getAustralian() {
		return $this->datetime->format($this->getLocale()->localFormat());
	}

	public function isValid() {
		return $this->datetime instanceof DateTime;
	}

}
