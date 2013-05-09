<?php
/**
 * Defined interface for both Alex's mapper Collection class and my
 * Schoolbox\Collection class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */
interface ICollection extends Iterator, Countable {
	public function add($value);
}