<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Alaress\Async\Notification;

interface LoggerInterface extends \Psr\Log\LoggerInterface {

	/**
	 * @param string $topic
	 * @return self
	 */
	public function setTopic($topic);

}