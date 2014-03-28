<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Recurrence;


use InvalidArgumentException;

class Recurrence {

	protected $minute = null;
	protected $hour = null;
	protected $dayOfMonth = null;
	protected $month = null;
	protected $dayOfWeek = null;


	/**
	 * Construct a recurrence given a crontab
	 *
	 * @param string $tab
	 * @throws \InvalidArgumentException
	 */
	public function __construct($tab) {

		$regex = '`[0-9]+|\*`';

		if(preg_match_all($regex, $tab, $matches) != 5) {
			throw new InvalidArgumentException("Tab should contain 5 integer or asterisk arguments");
		}

		$validation = array(
			'minute'     => 59,
			'hour'       => 23,
			'dayOfMonth' => 31,
			'month'      => 12,
			'dayOfWeek'  => 6
		);

		$matches = array_combine(array_keys($validation), $matches[0]);

		foreach($matches as $k => $v) {

			if($v == '*') {
				$this->$k = null;
			}
			elseif($v <= $validation[$k]) {
				$this->$k = $v;
			}
			else {
				$errors[] = "$k must be <= {$validation[$k]}, $v given";
			}
		}

		if(isset($errors)) {
			throw new InvalidArgumentException('Invalid crontab given: '.implode('; ', $errors).'.');
		}

	}


	/**
	 * Returns the UNIX TIMESTAMP of when this recurrence will NEXT fire.
	 *
	 * @return int
	 */
	public function getNextTimestamp() {
		// TODO ... figure out how to do this
	}

} 