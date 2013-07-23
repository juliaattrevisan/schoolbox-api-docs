<?php
/**
 * Alaress_Date_Relative
 *
 * A particular case of Alaress_Date_Format which handles a difference in time
 * and presents it as a human-friendly text string.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Date_Relative extends Alaress_Date_Format {
	private $prefix = 'on';
	private $suffix = 'ago';
	private $now = 0;

    private $intervals = array(
        array(60, 'second'),
        array(60, 'minute'),
        array(24, 'hour'),
        array(7,  'day'),
        array(4.38,  'week'),
        array(12, 'month'),
    );

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

		/* Presume a db datetime stamp
		 *
		 * Input format needs to all be in lower case for James' ninja code
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

        if($diff > 0) {
            return $this->getRelativePast($diff);
        }
        elseif($diff < 0) {
            return $this->getRelativeFuture($diff);
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
    private function getRelativePast($diff) {
        for($i = 0; $i < count($this->intervals); ++$i) {
            if ($diff < $this->intervals[$i][0]) {
                return sprintf('%s %s%s %s', $diff, $this->intervals[$i][1], $this->plural($diff), $this->getSuffix());
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
        return $this->getPrefix() . ' ' . $this->getDateOnly();
    }

    /**
     * Output a relative time in the future
     * @param $diff
     * @return string
     */
    private function getRelativeFuture($diff) {
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
        return $this->getPrefix() . ' ' . $this->getDateOnly();
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