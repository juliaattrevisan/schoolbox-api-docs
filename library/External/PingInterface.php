<?php
/**
 * External_PingInterface
 *
 * An interface for external databases to return whether or not they are alive
 * and accessible.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
interface External_PingInterface {
	/**
	 * @return bool
	 */
	public function isResponsive();
}
