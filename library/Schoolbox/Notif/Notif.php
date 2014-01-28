<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif;


use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SwiftMailerHandler;
use Monolog\Logger;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Schoolbox\Collection;
use Schoolbox\ConfigLoader;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Handler\AMQPHandler;
use Schoolbox\Notif\Handler\MySQLHandler;
use Schoolbox\Notif\MQ\Connector as MQConnector;

/**
 * This class is intentionally not a Singleton. It should be instantiated once only, yes, but then DI'd around.
 *
 * Class Notif
 * @package Schoolbox\Notif
 */
class Notif implements LoggerInterface {

	const CHANNEL_SYSTEM       = 'sys';     // notifications about top-level events
	const CHANNEL_NOTIFICATION = 'notif';   //     "           "   the notification system
	const CHANNEL_DATABASE     = 'db';      //     "           "   internal database events
	const CHANNEL_APPLICATION  = 'sb';      //     "           "   Schoolbox application events
	const CHANNEL_SYNERGY      = 'syn';     //     "           "   synergy database events
	const CHANNEL_ADMIN        = 'admin';   //     "           "   superuser events (includes assumption)
	const CHANNEL_AUTH         = 'login';   //     "           "   authentication events
	const CHANNEL_CMS          = 'cms';     //     "           "   cms events to be logged

	/** @var IConfigLoader */
	protected $conf;

	/** @var string */
	protected $channel;

	/** @var AMQPHandler */
	protected $amqp;

	/** @var SwiftMailerHandler  */
	protected $mail;

	/** @var StreamHandler */
	protected $syslog;

	public function __construct($channel = self::CHANNEL_SYSTEM, $configLoader = null) {
		$this->channel = $channel;
		$this->conf = $configLoader ?: new ConfigLoader();
		$this->instantiateHandlers();
	}

	/**
	 * Generates handlers for the various kinds of notifying
	 */
	protected function instantiateHandlers() {
		// message queue handling
		try {
			$this->amqp = new AMQPHandler(new MQConnector());
			$this->amqp->setFormatter(new JsonFormatter());
		}
		catch(AMQPRuntimeException $e) {
			$this->amqp = false;
		}

		// email handling
		$this->mail = new SwiftMailerHandler(
			new \Swift_Mailer(\Mailer::getSwiftTransport()),
			\Swift_Message::newInstance('Schoolbox Log', null, 'text/plain', 'utf-8')
				->addFrom('notif@' . $this->conf->get('site_hostname') . '.' . $this->conf->get('site_domain'))
				->addTo('matt.day+notif@alaress.com.au')
		);

		$this->syslog = new StreamHandler(fopen($this->conf->get('logs_dir') . '/mono.log', 'a'), Logger::NOTICE);

	}

	public function getChannel() {
		return $this->channel;
	}

	public function setChannel($channel) {
		$this->channel = $channel;
		return $this;
	}

	/**
	 * Returns the contents of the named queue
	 * @param $queueName
	 * @param bool $markAsRead
	 * @return Collection
	 */
	public function readQueue($queueName, $markAsRead = true) {

		$conn = $this->amqp->getMQConnector();
		$conn->declareQueue($queueName);

		$msgs = new Collection();

		while($m = $conn->readQueue($queueName, $markAsRead)) {
			/** @var AMQPMessage $m */
			$msgs->add(array_merge($m->delivery_info, array('body' => $m->body)));
		}

		return $msgs;
	}

	/**
	 * @param Logger $logger
	 */
	protected function appendHandlers($logger) {

		$logger->pushHandler($this->syslog);

		// the notification system shouldn't attempt to publish to itself (or try if it's down)
		if($this->channel == self::CHANNEL_NOTIFICATION || $this->amqp === false) {
			$logger->pushHandler($this->mail);
		}
		else {
			$logger->pushHandler($this->amqp);
		}

		switch($this->channel) {
			case self::CHANNEL_AUTH:
				$sqlh = new MySQLHandler(Logger::INFO);
				$sqlh->setQuery("INSERT INTO login_log (username, date, ip, login) VALUES (:username, NOW(), :ip, :login)");
				$logger->pushHandler($sqlh);
				break;
			case self::CHANNEL_CMS:
				$sqlh = new MySQLHandler(Logger::INFO);
				$sqlh->setQuery("
					INSERT INTO cmsLog
						(`object`, `objectId`, `action`, `user`)
					VALUES
						(:object, :objectId, :action, :user)
				");
				$logger->pushHandler($sqlh);
				break;
		}

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
		$this->appendHandlers($log);
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