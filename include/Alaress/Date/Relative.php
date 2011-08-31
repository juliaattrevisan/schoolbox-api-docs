<?php

class Alaress_Date_Relative extends Alaress_Date_Format {
	private $prefix = 'on';
	private $suffix = 'ago';
	private $now = 0;

	/**
	 * Constructor
	 * Does nothing, so allows you to set options
	 *
	 * @param  $dateTime
	 */
	public function __construct($dateTime) {

		/* If it's a unix timestamp, just convert it to a standard datetime
		 * then use parent methods
		 */
		if (is_numeric($dateTime)) {
			$dateTime = date('Y-m-d H:i:s', $dateTime);
		}

		/* Input format needs to all be in lower case for James' ninja code
		 */
		parent::__construct($dateTime, 'y-m-d h:i:s');
	}

	/**
	 * Ported from stdfuncs
	 *
	 * @param  $date
	 * @param int $now
	 * @param string $prefix
	 * @param string $suffix
	 * @return string
	 */
	public function getRelativeTime() {
		$currentTime = $this->getNow();

		/* If Now is zero, assume you want to get the period between the event
		 * happening and the current moment in time
		 */
		if (!$currentTime) {
			$currentTime = time();
		}

		/* Provide return string
		 */
		$diff = $currentTime - $this->getUnixTimestamp();

		if ($diff < 60) {
			return $diff . ' second' . $this->plural($diff) . ' ' . $this->getSuffix();
		}

		$diff = round($diff/60);

		if ($diff < 60) {
			return $diff . ' minute' . $this->plural($diff) . ' ' . $this->getSuffix();
		}

		$diff = round($diff/60);

		if ($diff < 24) {
			return $diff . ' hour' . $this->plural($diff) . ' ' . $this->getSuffix();
		}

		$diff = round($diff/24);

		if ($diff < 2) {
			return 'Yesterday';
		}

		if ($diff < 7) {
			return $diff . ' days ' . $this->getSuffix();
		}

		$diff = round($diff/7);

		if ($diff < 2) {
			return 'Last week';
		}

		if ($diff < 4) {
			return $diff . ' weeks ' . $this->getSuffix();
		}

		/* If it can't be measured using weeks, just return the date using the
		 * superclass methods
		 */
		return $this->getPrefix() . ' ' . $this->getAustralian();
		//date(Cfg::get('date_notime'), $date);
	}

	/**
	 * Pluralise something. Used in getRelativeTime()
	 *
	 * @param  $num
	 * @return string
	 */
	private function plural($num) {
		if ($num != 1) {
			return 's';
		}

		return '';
	}

	/**
	 * Set prefix
	 * Support chainable settings
	 *
	 * @param  $val
	 * @return Alaress_Date_Relative
	 */
	public function setPrefix($val) {
		$this->prefix = $val;

		return $this;
	}

	/**
	 * Get prefix
	 *
	 * @return string
	 */
	private function getPrefix() {
		return $this->prefix;
	}

	/**
	 * Set suffix
	 * Support chainable settings
	 *
	 * @param  $val
	 * @return Alaress_Date_Relative
	 */
	public function setSuffix($val) {
		$this->suffix = $val;

		return $this;
	}

	/**
	 * Get suffix
	 *
	 * @return string
	 */
	private function getSuffix() {
		return $this->suffix;
	}

	/**
	 * Set Now
	 * Support chainable settings
	 *
	 * @param  $val
	 * @return Alaress_Date_Relative
	 */
	public function setNow($val) {
		$this->now = $val;

		return $this;
	}

	/**
	 * Get Now
	 *
	 * @return int
	 */
	public function getNow() {
		return $this->now;
	}
}