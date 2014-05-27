<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message;

/**
 * Class MessageBody

 * @deprecated
 * @package Schoolbox\Notif\Message
 */
class MessageBody {

	/** @var string */
	protected $messageString;

	/** @var array */
	protected $tokens = array();

	/**
	 * @deprecated
	 * @param string $messageString
	 */
	public function __construct($messageString) {
		$this->messageString = $messageString;
		if(preg_match_all('`:([A-Za-z_][A-Za-z0-9_]*)`', $messageString, $matches)) {
			$this->tokens = $matches[1];
		}
	}

	public function getMessageString() {
		return $this->messageString;
	}

	public function getTokenList() {
		return $this->tokens;
	}

} 