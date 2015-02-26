<?php
/**
 * Alaress_Date_V52
 *
 * Stock-standard way of doing it, for backwards compatibility when not using
 * PHP 5.3 which has that wacky DateTime object.
 *
 * Uses the Alaress_Date_Format_Interface to enforce method definition
 * Localisation for date formats
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Date_Strategy_V52 extends Alaress_Date_Strategy_Abstract {

	/**
	 * Ported properties from Alaress_Date_Relative
	 */

	/**
	 * @var string
	 */
	private $prefix = 'on';

	/**
	 * @var string
	 */
	private $suffix = 'ago';

	/**
	 * @var int
	 */
	private $now = 0;

	/**
	 * @var array
	 */
	private $intervals = array(
		array(60, 'second'),
		array(60, 'minute'),
		array(24, 'hour'),
		array(7,  'day'),
		array(4.38,  'week'),
		array(12, 'month'),
	);

	/**
	 * Transform the datetime into the format specified in the config table
	 * Do some error checking
	 *
	 * @return string
	 */
	public function getDatabase() {
		$result = parent::getDatabase();

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
	 * {@inheritdoc}
	 */
	public function getRelativeTime($now = null, $stripModifiers = false)
	{
		$currentTime = null;
		if ($now instanceof DateTime || $now instanceof DateTimeInterface) {
			// now should be something understandable as a datetime
			$currentTime = $now->getTimestamp();
		} elseif ($now instanceof Alaress_Date_Format) {
			$currentTime = $now->getUnixTimestamp();
		}
		if (!isset($time)) {
			// error out
			$currentTime = time();
		}

//		$currentTime = $this->getNow();
//		/* If Now is zero, assume you want to get the period between the event
//		 * happening and the current moment in time
//		 */
//		if (!$currentTime) {
//			$currentTime = time();
//		}

		/* Provide return string
		 */
		$diff = $currentTime - $this->getUnixTimestamp();

		if($diff > 0) {
			return $this->getRelativePast($diff, $stripModifiers);
		}
		elseif($diff < 0) {
			return $this->getRelativeFuture($diff, $stripModifiers);
		}
		else {
			return 'just then';
		}
	}

	/**
	 * Output a relative string from the past
	 * @param $diff
	 * @return string
	 */
	private function getRelativePast($diff, $stripModifiers = false) {
		$suffix = $stripModifiers ? '' : $this->suffix;
		for($i = 0; $i < count($this->intervals); ++$i) {
			if ($diff < $this->intervals[$i][0]) {
				return sprintf('%s %s%s %s', $diff, $this->intervals[$i][1], $this->plural($diff), $suffix);
			}
			$diff = round($diff / $this->intervals[$i][0]);

			// exceptional cases
			if($i == 2) {
				if ($diff < 2) {
					return 'Yesterday';
				}
			}
			elseif($i == 3) {
				if ($diff < 2) {
					return 'Last week';
				}
			}
		}

		// fall back to full format
		return ($stripModifiers ? '' : $this->prefix) . ' ' . $this->getDateOnly();
	}

	/**
	 * Output a relative time in the future
	 * @param $diff
	 * @return string
	 */
	private function getRelativeFuture($diff, $stripModifiers = false) {
		// ensure positivity
		if($diff < 0) {
			$diff = 0 - $diff;
		}

		for($i = 0; $i < count($this->intervals); ++$i) {
			if ($diff < $this->intervals[$i][0]) {
				return sprintf('in %s %s%s', $diff, $this->intervals[$i][1], $this->plural($diff));
			}
			$diff = round($diff / $this->intervals[$i][0]);

			// exceptional cases
			if($i == 2) {
				if ($diff < 2) {
					return 'Tomorrow';
				}
				elseif($diff < 7) {
					$dow = $this->getUsingFormat('l');
					return 'on ' . $dow;
				}
				elseif($diff < 14) {
					$dow = $this->getUsingFormat('l');
					return 'next ' . $dow;
				}
			}
		}

		// fall back to full format
		return ($stripModifiers ? '' : $this->prefix) . ' ' . $this->getDateOnly();
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
}
