<?php
/**
 * Alaress_Date_Locale_Interface
 *
 * This defines universal methods which all localisations must use
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

interface Alaress_Date_Locale_Interface {
	public function localShortFormat();
	public function longFormat();
	public function shortFormat();
	public function dateOnlyFormat();
	public function timeOnlyFormat();
	public function databaseFormat();
	public function databaseDateFormat();
	public function databaseTimeFormat();
	public function timestampFormat();

	public function localFormat();
	public function longDateOnlyFormat();
}
