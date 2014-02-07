<?php
/**
 * StatusChange
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\ActivityLog;


class StatusChange extends Base {

	/**
	 * Base message
	 *
	 * @var string
	 */
	protected $text = ' updated status to ';

	/**
	 * Construct message
	 */
	protected function setup() {
		$str = $this->getActioner()->getFullname() . $this->getText() . $this->getEvent()->getMetadata()->getStatus()->getName();

		$this->setMessage($str);
	}
}