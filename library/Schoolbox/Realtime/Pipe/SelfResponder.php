<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;


use Schoolbox\Realtime\Stomp\SubscriptionManager;

class SelfResponder extends Responder {

	/** @var PipeManager */
	protected $pipeManager;

	/** @var Pipe */
	protected $pipe;


	/**
	 * @param PipeManager $pipeManager
	 * @param Pipe $pipe
	 */
	public function __construct($pipeManager, $pipe) {
		$this->pipeManager = $pipeManager;
		$this->pipe = $pipe;
	}

	public function notifyServer($data) {
		// noop
	}

	public function notifyPipe($data) {
		$this->pipeManager->sendToPipe($this->pipe, $data);
	}

	public function notifySession($data) {
		$this->pipeManager->sendToSession($this->pipe->getSession(), $data);
	}

	public function notifyUser($data) {
		$this->pipeManager->sendToUser($this->pipe->getUser(), $data);
	}


} 