<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Daemon;


use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Notif\MQ\Connector as MQConnector;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\NotifException;
use Schoolbox\User;

class MQLauncher {

	protected $os;

	/** @var MQConnector */
	protected $conn;


	/**
	 * Creates an MQLauncher with an optional output stream.
	 *
	 * @param resource $outputStream - an outputstream through which to pipe verbosity. If null, no verbosity.
	 */
	public function __construct($outputStream = null) {
		$this->os = $outputStream;
	}

	/**
	 * Checks that a RabbitMQ server is running, and if not, attempts to bring one up.
	 * As such this method is idempotent.
	 *
	 * @return bool
	 */
	public function startServer() {

		// check for a rabbitmq instance
		if(strlen(`pgrep rabbitmq`)) {
			$this->log("RabbitMQ server instance found.");
			return true;
		}

		$this->log("RabbitMQ server instance not found. Launching.");

		// TODO this requires sudo, either need to modify sudoers file or find another way to do this
		if(strlen(`rabbitmq-server 2>&1 1> /dev/null &`)) {
			$this->log('Failed to launch RabbitMQ server. Aborting.', Logger::CRITICAL);
			$notif = new Notif(Notif::CHANNEL_NOTIFICATION);
			$notif->critical('Failed attempt to launch RabbitMQ');
			return false;
		}

		return true;
	}

	/**
	 * Spools the various message queues for Schoolbox. Idempotent method.
	 *
	 * @var Collection $userCollection
	 * @return bool
	 */
	public function spool($userCollection) {

		$this->conn = new MQConnector();

		$this->log('Spooling user queues.');



		// every user has a queue, which is identified as "u.{username}"
		foreach($userCollection as $user) {
			/** @var User $user */

			// declare the user's queue with the pattern "u.{username}"
			$userQueue = 'u.'.$user->getUsername();


			try {
				$this->conn->declareQueue($userQueue);
			}
			catch(NotifException $e) {
				$this->log($e->getMessage(), Logger::ERROR);
			}

			// create bindings for every user

			// most basic is direct notification
			$bindingKeys = array('notify.user.'.$user->getId());

			// TODO lookup group memberships and add respective binding keys for each



			foreach($bindingKeys as $key) {
				$this->conn->bindQueue($userQueue, $key);
			}

			// TODO REMOVE binding keys for revoked group memberships
//			$this->conn->unbindQueue($userQueue, $unsetKey);

			// register the event callback
			$this->conn->setConsumerCallback($userQueue, array($this, 'deliver'));
		}

		return true;
	}

	public function listen() {

		$this->log('Listening...');

		$channel = $this->conn->getChannel();

		while(count($channel->callbacks)) {
			$channel->wait();
		}

		$this->log('Closing connection.');

		$channel->close();
		$this->conn->closeConnection();

	}

	public function deliver($msg) {
		// dummy for now

		$this->log(
			sprintf("Received '%s' via `%s`.", $msg->body, $msg->delivery_info['routing_key']), Logger::DEBUG
		);


	}

	/**
	 * Write to the outputstream, if it exists.
	 *
	 * @param $message
	 * @param int $severity
	 */
	protected function log($message, $severity = Logger::INFO) {

		$severities = array(
			Logger::EMERGENCY => '*',
			Logger::ALERT     => '*',
			Logger::CRITICAL  => '*',
			Logger::ERROR     => '!',
			Logger::WARNING   => '!',
			Logger::NOTICE    => '+',
			Logger::INFO      => ' ',
			Logger::DEBUG     => '-'
		);

		if(!is_null($this->os)) {
			fwrite($this->os, "[{$severities[$severity]}] $message\n");
		}
	}



} 