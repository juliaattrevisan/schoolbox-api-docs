<?php
/**
 * Alaress_Date_Locale_Interface
 *
 * This defines universal methods which all localisations must use
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

interface Alaress_Date_Locale_Interface {
	public function localShortFormat();
	public function longFormat();
	public function shortFormat();
	public function dateOnlyFormat();
	public function timeOnlyFormat();

	public function localFormat();
	public function longDateOnlyFormat();
    public function shortDateOnlyFormat();

	public function calendarDate();
	public function calendarDateTime();

    public function weekendDays();
}
