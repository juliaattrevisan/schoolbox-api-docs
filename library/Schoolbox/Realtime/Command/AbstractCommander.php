<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Command;


use Schoolbox\Notif\Notifactory;

abstract class AbstractCommander implements CommanderInterface {

	/** @var string */
	protected $topic;

	/**
	 * @param string $topic
	 */
	public function __construct($topic) {
		$this->topic = $topic;
	}

	/**
	 * @param string $cmd
	 * @param mixed $id
	 */
	protected function advise($cmd, $id = null) {
		$n = Notifactory::create($this->topic);

		$message = $cmd . ($id ? $id : '');
		$n->warning($message);
	}

}
