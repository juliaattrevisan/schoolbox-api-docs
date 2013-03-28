<?php
/**
 * Implementation of Edumate Class connector
 * For schema information, see http://wiki.schoolbox.com.au/index.php/Interface_Calls
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class External_Edumate_Ping extends External_Edumate_Connection implements External_PingInterface {
	public function isResponsive() {
		try {
			parent::__construct();
			return true;
		}
		catch (Exception $e) {}
		return false;
	}
}
