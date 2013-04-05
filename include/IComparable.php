<?php
/**
 * Base interface for objects which are comparable to each other.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
interface IComparable {
	/**
	 * Compares two objects, ideally of the same type.
	 *
	 * @param $other
	 *
	 * @return int -1 if $this < $other, 1 if $this > $other, 0 if $this = $other
	 */
	public function compare($other);
}