<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif;


use Monolog\Logger;
use PhpAmqpLib\Connection\AMQPConnection;
use Psr\Log\LoggerInterface;
use Schoolbox\Notif\Handler\AMQPHandler;

/**
 * This class is intentionally not a Singleton. It should be instantiated once only, yes, but then DI'd around.
 *
 * Class Notif
 * @package Schoolbox\Notif
 */
class Notif implements LoggerInterface {

	/** @var string */
	protected $channel;

	/** @var AMQPHandler */
	protected $amqp;

	public function __construct($channel = 'system') {
		$this->amqp = new AMQPHandler(new AMQPConnection('localhost', 5672, 'guest', 'guest'));
		$this->channel = $channel;
	}

	public function getChannel() {
		return $this->channel;
	}

	public function setChannel($channel) {
		$this->channel = $channel;
		return $this;
	}

	/**
	 * Logs the given message to the specified channel.
	 * Convenience method equivalent to setChannel()->log();
	 *
	 * @param string $channel
	 * @param int $level
	 * @param string $message
	 * @param array $context
	 * @return bool
	 */
	public function logTo($channel, $level, $message, array $context = array()) {
		$this->setChannel($channel);
		return $this->log($level, $message, $context);
	}

	public function log($level, $message, array $context = array()) {
		$log = new Logger($this->channel);
		$log->pushHandler($this->amqp);
		return $log->addRecord($level, $message, $context);
	}

	/**
	 * Detailed debug information.
	 *
	 * @param $message
	 * @param array $context
	 * @return mixed
	 */
	public function debug($message, array $context = array()) {
		return $this->log(Logger::DEBUG, $message, $context);
	}

	/**
	 * Interesting events. Examples: User logs in, SQL logs.
	 *
	 * @param $message
	 * @param array $context
	 * @return mixed
	 */
	public function info($message, array $context = array()) {
		return $this->log(Logger::INFO, $message, $context);
	}

	/**
	 * Normal but significant events.
	 *
	 * @param $message
	 * @param array $context
	 * @return mixed
	 */
	public function notice($message, array $context = array()) {
		return $this->log(Logger::NOTICE, $message, $context);
	}

	/**
	 * Exceptional occurrences that are not errors.
	 * Examples: Use of deprecated APIs, poor use of an API, undesirable things that are not necessarily wrong.
	 *
	 * @param $message
	 * @param array $context
	 * @return mixed
	 */
	public function warning($message, array $context = array()) {
		return $this->log(Logger::WARNING, $message, $context);
	}

	/**
	 * Runtime errors that do not require immediate action but should typically be logged and monitored.
	 *
	 * @param $message
	 * @param array $context
	 * @return mixed
	 */
	public function error($message, array $context = array()) {
		return $this->log(Logger::ERROR, $message, $context);
	}

	/**
	 * Critical conditions.
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param $message
	 * @param array $context
	 * @return mixed
	 */
	public function critical($message, array $context = array()) {
		return $this->log(Logger::CRITICAL, $message, $context);
	}

	/**
	 * Action must be taken immediately.
	 * Example: Entire website down, database unavailable, etc. This should trigger the SMS alerts and wake you up.
	 *
	 * @param $message
	 * @param array $context
	 * @return mixed
	 */
	public function alert($message, array $context = array()) {
		return $this->log(Logger::ALERT, $message, $context);
	}

	/**
	 * Emergency: system is unusable.
	 *
	 * @param $message
	 * @param array $context
	 * @return mixed
	 */
	public function emergency($message, array $context = array()) {
		return $this->log(Logger::EMERGENCY, $message, $context);
	}

}