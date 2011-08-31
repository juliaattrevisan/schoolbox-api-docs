<?php
/**
 * Alaress_Date_Interface
 *
 * Methods used in your app for Alaress_Date_Format
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
interface Alaress_Date_Format_Interface {
	public function getUnixTimestamp();
	public function getDatabase();
	public function getDateOnly();
	public function getAustralian();
}