<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif;


use Mailer;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SwiftMailerHandler;
use Monolog\Logger;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Formatter\CompressedFormatter;
use Schoolbox\Notif\Handler\AMQPHandler;
use Schoolbox\Notif\Handler\DigestHandler;
use Schoolbox\Notif\Handler\MessageHandler;
use Schoolbox\Notif\Handler\MySQLHandler;
use Schoolbox\Notif\MQ\Connector as MQConnector;
use Swift_Mailer;
use Swift_Message;

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

	/** @var MQConnector */
	protected $mqc = null;

	/** @var HandlerInterface[] */
	protected $handlers;

	public function __construct($topic = self::TOPIC_SYSTEM, $configLoader = null) {
		$this->topic = $topic;
		$this->topicFactory = new TopicFactory();
		$this->conf = $configLoader ?: ConfigMapper::getInstance();
		$this->instantiateHandlers();
	}

	/**
	 * Generates handlers for the various kinds of notifying
	 */
	protected function instantiateHandlers() {
		// message queue handling
		try {

			// if the rabbit cannot be contacted, this will throw an exception
			$this->mqc = new MQConnector();

			// the rabbit is only interested in notices and above,
			$this->amqp = new AMQPHandler($this->mqc, Logger::NOTICE, false);
		}
		catch(AMQPRuntimeException $e) {
			$this->amqp = false;
		}

		return;

//		$from = 'notif@' . $this->conf->get('site_hostname') . '.' . $this->conf->get('site_domain');

		$m = new Swift_Message('Schoolbox Log', null, 'text/plain','utf-8');
		$m->setTo($this->conf->get('mail_support_address'));
		$m->setFrom('notif@alaress.com.au'); // TODO restore this to $from

		// digest handling
		// $this->digest = ...?

		// email handling (allow bubble through to rabbit)
		$this->mail = new SwiftMailerHandler(new Swift_Mailer(Mailer::getSwiftTransport(true)), $m, Logger::WARNING, true);

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


	/**
	 * Return an array of handlers based on the current configuration of self, created in ascending order of priority.
	 * Spawns handlers on demand.
	 *
	 * @return HandlerInterface[]
	 */
	protected function getHandlers() {
		$stack = array();

		// [0] top priority: system failure
		if(!isset($this->handlers['fatalMailer'])) {
			$fatalEmail = new Swift_Message('Schoolbox is Down', null, 'text/plain', 'utf-8');
			$fatalEmail->setTo($this->conf->get('mail_support_address'));
			$fatalEmail->setFrom('notif@alaress.com.au');

			$this->handlers['fatalMailer'] = new SwiftMailerHandler(new Swift_Mailer(
				Mailer::getSwiftTransport(true)), $fatalEmail, Logger::EMERGENCY, false
			);
		}

		array_push($stack, $this->handlers['fatalMailer']);


		// [1] system warning
		if(!isset($this->handlers['sysMailer'])) {
			$sysEmail = new Swift_Message('Schoolbox Requires Your Attention', null, 'text/plain', 'utf-8');
			$sysEmail->setTo($this->conf->get('mail_support_address'));
			$sysEmail->setFrom('notif@alaress.com.au');

			$this->handlers['sysMailer'] = new SwiftMailerHandler(new Swift_Mailer(
				Mailer::getSwiftTransport(true)), $sysEmail, Logger::ALERT, false
			);
		}

		array_push($stack, $this->handlers['sysMailer']);


		// [2] apply system handlers if it's a recognised topic, or user-specific if not
		if($this->isSystemTopic($this->topic)) {

			// [2a] internal application message
			if($this->topic == self::TOPIC_APPLICATION) {
				if(!isset($this->handlers['amqp'])) {
					$this->handlers['amqp'] = new AMQPHandler($this->mqc, Logger::NOTICE, false);
				}
				array_push($stack, $this->handlers['amqp']->setFormatter(new CompressedFormatter()));
			}

			// [2b] sqllog (this one doesn't get stored globally)
			if($sqllog = $this->getSqlHandler(Logger::NOTICE, false)) {
				array_push($stack, $sqllog);
			}

			// [2c] syslog (neither does this one)
			$logfile = sprintf("%s/%s.log", $this->conf->get('logs_dir'), $this->topic);
			$syslog = new StreamHandler(fopen($logfile, 'a'), Logger::INFO, false);

			array_push($stack, $syslog);
		}
		else {
			try {
				// [2c] instant user message (notice, warning or error)
				if(!isset($this->handlers['instant'])) {
					$this->handlers['instant'] = new MessageHandler($this->mqc, Logger::NOTICE, false);
				}
				array_push($stack, $this->handlers['instant']);

				// [2d] digest user message
				if(!isset($this->handlers['digest'])) {
					$this->handlers['digest'] = new DigestHandler($this->mqc, Logger::INFO, false);
				}
				array_push($stack, $this->handlers['digest']);
			}
			catch(NotifException $e) {
				// no connection to rabbit available. We should be able to safely ignore this
			}
		}


		// [3] bottom priority: debugger
		if(!isset($this->handlers['debugMailer'])) {
			$debugEmail = new Swift_Message('Schoolbox Debug', null, 'text/plain', 'utf-8');
			$debugEmail->setTo($this->conf->get('mail_support_address'));
			$debugEmail->setFrom('notif@alaress.com.au');

			$this->handlers['debugMailer'] = new SwiftMailerHandler(new Swift_Mailer(
					Mailer::getSwiftTransport(true)), $debugEmail, Logger::DEBUG, false
			);
		}

		array_push($stack, $this->handlers['debugMailer']);

		return $stack;
	}


	/**
	 * Only returns an SQL handler if it's relevant to the topic
	 *
	 * @param string|int $level
	 * @param bool $bubble
	 * @return MySQLHandler
	 */
	protected function getSqlHandler($level, $bubble = true) {

		$sql = new MySQLHandler($level, $bubble);

		switch($this->topic) {
			// when someone logs in, or fails to
			case self::TOPIC_AUTH:
				$sql->setQuery("
					INSERT INTO `login_log`
						(`username`, `date`, `ip`, `login`)
					VALUES
						(:username, NOW(), :ip, :login)
				");
				return $sql;

			// when cms-specific things are logged
			case self::TOPIC_CMS:
				$this->sql->setQuery("
					INSERT INTO `cmsLog`
						(`object`, `objectId`, `action`, `user`)
					VALUES
						(:object, :objectId, :action, :user)
				");
				return $sql;
		}

		return null;
	}


	/**
	 * @param string $topic
	 * @return bool
	 */
	public function isSystemTopic($topic) {
		if(!isset($this->topic_cache)) {
			$this->topic_cache = array();
			// use a little reflection to get the available constants
			$rf = new \ReflectionClass($this);
			foreach($rf->getConstants() as $name => $value) {
				$this->topic_cache[$name] = $value;
			}
		}
		return in_array($topic, $this->topic_cache);
	}


	/**
	 * Logs the given message to the specified channel.
	 * Convenience method equivalent to setChannel()->log();
	 *
	 * @param string $topic
	 * @param int $level
	 * @param string $message
	 * @param array $context
	 * @return bool
	 */
	public function logTo($topic, $level, $message, array $context = array()) {
		$this->setTopic($topic);
		return $this->log($level, $message, $context);
	}

	public function log($level, $message, array $context = array()) {
		$log = new Logger($this->topic, $this->getHandlers());
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