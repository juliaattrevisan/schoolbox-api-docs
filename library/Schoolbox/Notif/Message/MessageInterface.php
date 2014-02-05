<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message;


use Serializable;

interface MessageInterface extends Serializable {

	/**
	 * @return bool
	 */
	public function send();

	/**
	 * Return the serialized form of this message.
	 *
	 * @return string
	 */
	public function __toString();


	/**
	 * @param array $context
	 * @return void
	 */
	public function setContext($context = array());
} 