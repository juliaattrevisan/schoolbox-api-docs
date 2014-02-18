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
use Psr\Log\LoggerInterface;
use Schoolbox\ConfigLoader;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Formatter\CompressedFormatter;
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

	const TOPIC_SYSTEM       = 'sys';     // notifications about top-level events
	const TOPIC_NOTIFICATION = 'notif';   //     "           "   the notification system
	const TOPIC_DATABASE     = 'db';      //     "           "   internal database events
	const TOPIC_APPLICATION  = 'sb';      //     "           "   Schoolbox application events (ie. Mappers)
	const TOPIC_SYNERGY      = 'syn';     //     "           "   synergy database events
	const TOPIC_ADMIN        = 'admin';   //     "           "   superuser events (includes assumption)
	const TOPIC_AUTH         = 'login';   //     "           "   authentication events
	const TOPIC_CMS          = 'cms';     //     "           "   cms events to be logged

	/** @var IConfigLoader */
	protected $conf;

	/** @var string */
	protected $topic;

	/** @var TopicFactory */
	protected $topicFactory;

	/** @var AMQPHandler */
	protected $amqp;

	/** @var SwiftMailerHandler  */
	protected $mail;

	/** @var  MySQLHandler */
	protected $sql;

	/** @var StreamHandler */
	protected $syslog;

	public function __construct($topic = self::TOPIC_SYSTEM, $configLoader = null) {
		$this->topic = $topic;
		$this->topicFactory = new TopicFactory();
		$this->conf = $configLoader ?: new ConfigLoader();
		$this->instantiateHandlers();
	}

	/**
	 * Generates handlers for the various kinds of notifying
	 */
	protected function instantiateHandlers() {
		// message queue handling
		try {

			// the rabbit is only interested in notices and above,
			$this->amqp = new AMQPHandler(new MQConnector(), Logger::NOTICE, false);
		}
		catch(AMQPRuntimeException $e) {
			$this->amqp = false;
		}

		$from = 'notif@' . $this->conf->get('site_hostname') . '.' . $this->conf->get('site_domain');

		$m = new \Swift_Message('Schoolbox Log', null, 'text/plain','utf-8');
		$m->setTo($this->conf->get('mail_support_address'));
		$m->setFrom('notif@alaress.com.au'); // TODO restore this to $from

		// digest handling
		// $this->digest = ...?

		// email handling (allow bubble through to rabbit)
		$this->mail = new SwiftMailerHandler(new \Swift_Mailer(\Mailer::getSwiftTransport(true)), $m, Logger::WARNING, true);

		// syslog handling (if this handles a message, it's done: no bubble)
		$this->syslog = new StreamHandler(fopen($this->conf->get('logs_dir') . '/mono.log', 'a'), Logger::NOTICE, false);

		// sql handling (allow bubble)
		$this->sql = new MySQLHandler(Logger::INFO, true);

	}

	public function getTopic() {
		return $this->topic;
	}

	public function setTopic($topic) {
		$this->topic = $topic;
		return $this;
	}

	public function selectFormatter() {
		if($this->topic == self::TOPIC_APPLICATION) {
			return new CompressedFormatter();
		}
		return new JsonFormatter();
	}

	/**
	 * @param Logger $logger
	 */
	protected function appendHandlers($logger) {

		// the push order of handlers is important: they get executed newest to oldest

		// syslog is bottom priority: other things will catch atop it
		$logger->pushHandler($this->syslog);

		// the notification system shouldn't attempt to publish to itself (or try if it's down)
		if($this->topic == self::TOPIC_NOTIFICATION || $this->amqp === false) {
			$logger->pushHandler($this->mail);
		}
		else {
			$logger->pushHandler($this->amqp->setFormatter($this->selectFormatter()));
		}

		// find out how we're logging this, based on which topic (channel) we're in
		switch($this->topic) {

			// when someone logs in, or fails to
			case self::TOPIC_AUTH:
				$this->sql->setQuery("
					INSERT INTO `login_log`
						(`username`, `date`, `ip`, `login`)
					VALUES
						(:username, NOW(), :ip, :login)
				");
				$logger->pushHandler($this->amqp);
				$logger->pushHandler($this->sql);
				break;

			// when cms-specific things are logged
			case self::TOPIC_CMS:
				$this->sql->setQuery("
					INSERT INTO `cmsLog`
						(`object`, `objectId`, `action`, `user`)
					VALUES
						(:object, :objectId, :action, :user)
				");
				$logger->pushHandler($this->amqp);
				$logger->pushHandler($this->sql);
				break;

			// When mappers CRUD, this is what happens
			case self::TOPIC_APPLICATION:
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
		$this->setTopic($channel);
		return $this->log($level, $message, $context);
	}

	public function log($level, $message, array $context = array()) {
		$log = new Logger($this->topic);
		$this->appendHandlers($log);
		try {
			return $log->addRecord($level, $message, $context);
		}
		catch(\Swift_TransportException $e) {
			// rabbit failed... email failed... at least it's logged to file
			return false;
		}
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


	/* Convenience methods for setting broadcast topics */

	/**
	 * @param $userId
	 * @return self
	 */
	public function targetUser($userId) {
		return $this->setTopic($this->topicFactory->targetUser($userId));
	}

	/**
	 * @param $categoryId
	 * @return self
	 */
	public function targetCategory($categoryId) {
		return $this->setTopic($this->topicFactory->targetCategory($categoryId));
	}

	/**
	 * @param $categoryId
	 * @return self
	 */
	public function targetCategoryAdmin($categoryId) {
		return $this->setTopic($this->topicFactory->targetCategoryAdmin($categoryId));
	}

	/**
	 * @param $categoryId
	 * @return self
	 */
	public function targetCategoryStaff($categoryId) {
		return $this->setTopic($this->topicFactory->targetCategoryStaff($categoryId));
	}

	/**
	 * @param $categoryId
	 * @return self
	 */
	public function targetCategoryStudents($categoryId) {
		return $this->setTopic($this->topicFactory->targetCategoryStudents($categoryId));
	}

	/**
	 * @param $campusId
	 * @return self
	 */
	public function targetCampus($campusId) {
		return $this->setTopic($this->topicFactory->targetCampus($campusId));
	}

	/**
	 * @param $campusId
	 * @return self
	 */
	public function targetCampusStaff($campusId) {
		return $this->setTopic($this->topicFactory->targetCampusStaff($campusId));
	}

	/**
	 * @param $campusId
	 * @return self
	 */
	public function targetCampusStudents($campusId) {
		return $this->setTopic($this->topicFactory->targetCampusStudents($campusId));
	}

	/**
	 * @param $campusId
	 * @return self
	 */
	public function targetCampusParents($campusId) {
		return $this->setTopic($this->topicFactory->targetCampusParents($campusId));
	}

	/**
	 * @param $roleId
	 * @return self
	 */
	public function targetRole($roleId) {
		return $this->setTopic($this->topicFactory->targetRole($roleId));
	}

	/**
	 * @param $roleType
	 * @return self
	 */
	public function targetRoleType($roleType) {
		return $this->setTopic($this->topicFactory->targetRoleType($roleType));
	}

	/**
	 * @param $topic
	 * @return self
	 */
	public function targetSuperusers($topic) {
		return $this->setTopic($this->topicFactory->targetSuperusers($topic));
	}

	/**
	 * @param $houseId
	 * @return self
	 */
	public function targetSchoolHouse($houseId) {
		return $this->setTopic($this->topicFactory->targetSchoolHouse($houseId));
	}

	/**
	 * @param $houseId
	 * @return self
	 */
	public function targetSchoolHouseStaff($houseId) {
		return $this->setTopic($this->topicFactory->targetSchoolHouseStaff($houseId));
	}

	/**
	 * @param $houseId
	 * @return self
	 */
	public function targetSchoolHouseStudents($houseId) {
		return $this->setTopic($this->topicFactory->targetSchoolHouseStudents($houseId));
	}

	/**
	 * @param $resId
	 * @return self
	 */
	public function targetResidentialHouse($resId) {
		return $this->setTopic($this->topicFactory->targetResidentialHouse($resId));
	}

	/**
	 * @param $resId
	 * @return self
	 */
	public function targetResidentialHouseStaff($resId) {
		return $this->setTopic($this->topicFactory->targetResidentialHouseStaff($resId));
	}

	/**
	 * @param $resId
	 * @return self
	 */
	public function targetResidentialHouseStudents($resId) {
		return $this->setTopic($this->topicFactory->targetResidentialHouseStudents($resId));
	}

	/**
	 * @param $userId
	 * @return self
	 */
	public function targetParentsOfStudent($userId) {
		return $this->setTopic($this->topicFactory->targetParentsOfStudent($userId));
	}

}