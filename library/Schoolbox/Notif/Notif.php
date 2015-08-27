<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif;


use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Lumberjack;
use ConfigFallback;
use Mailer;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SwiftMailerHandler;
use Monolog\Logger;
use PDOException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Formatter\CompressedFormatter;
use Schoolbox\Notif\Handler\AMQPHandler;
use Schoolbox\Notif\Handler\InstamailHandler;
use Schoolbox\Notif\Handler\MySQLHandler;
use Schoolbox\Notif\Handler\PushHandler;
use Schoolbox\Notif\MQ\Connector as MQConnector;
use Schoolbox\Realtime\Schedule\Persistence\QueueItem;
use Schoolbox\Realtime\Schedule\Persistence\QueueItemMapper;
use Swift_Mailer;
use Swift_Message;
use Swift_RfcComplianceException;

/**
 * This class is intentionally not a Singleton. It should be instantiated once only, yes, but then DI'd around.
 *
 * Class Notif
 * @package Schoolbox\Notif
 */
class Notif implements NotifInterface {

	/** Note - If you create any extra topics, ensure they are added to the following for auto create:
	 * tools/create-data-dirs.sh
	 */
	const TOPIC_SYSTEM       = 'sys';     // notifications about top-level events
	const TOPIC_NOTIFICATION = 'notif';   //     "           "   the notification system
	const TOPIC_DATABASE     = 'db';      //     "           "   internal database events
	const TOPIC_APPLICATION  = 'sb';      //     "           "   Schoolbox application events (ie. Mappers)
	const TOPIC_SYNERGY      = 'syn';     //     "           "   synergy database events
	const TOPIC_ADMIN        = 'admin';   //     "           "   superuser events (includes assumption)
	const TOPIC_AUTH         = 'login';   //     "           "   authentication events
	const TOPIC_CMS          = 'cms';     //     "           "   cms events to be logged

	const TOPIC_CMD_REROUTE  = 'cmd.reroute'; // "           "   async actions the scheduler needs to perform
	const TOPIC_CMD_DIGEST   = 'cmd.digest';  // "           "   async actions the digester needs to perform

	/** @var IConfigLoader */
	protected $conf;

	/** @var string */
	protected $topic;

	/** @var AMQPInterface */
	protected $mqc = null;

	/** @var HandlerInterface[] */
	protected $handlers;

    /** @var LoggerInterface */
    protected $log;

	/**
	 * @param string $topic
	 * @param IConfigLoader $configLoader
	 */
	public function __construct($topic = self::TOPIC_SYSTEM, $configLoader = null) {
		$this->topic = $topic;

		// use the provided config
		if($configLoader) {
			$this->conf = $configLoader;
		}
		// try for a config mapper instance
		elseif(extension_loaded('pdo')) {
			\Database::reinitialise();
			try {
				$this->conf = ConfigMapper::getInstance();
				$this->conf->get('mail_support_address');
			}
			catch(PDOException $e) {
				// if something PDO-esque broke, fall back
				$this->conf = new ConfigFallback();
			}
		}
		// fallback to hardcoded config vars
		else {
			$this->conf = new ConfigFallback();
		}

		try {
			// if the rabbit cannot be contacted, this will throw an exception
			$this->mqc = MQConnector::getInstance();
		}
		catch(AMQPRuntimeException $e) {
			$this->mqc = false;
		}

        $this->log = new Lumberjack\Logger(null);
	}


    /**
     * @param LoggerInterface $log
     * @return static
     */
    public function setLogger(LoggerInterface $log)
    {
        $this->log = $log;
        return $this;
    }


	/**
	 * @return string
	 */
	public function getTopic() {
		return $this->topic;
	}


	/**
	 * @param string $topic
	 * @return self
	 */
	public function setTopic($topic) {
		$this->topic = $topic;
		return $this;
	}


	/**
	 * @param string $title
	 * @param string $to
	 * @param string|array $from
	 * @param int $severity
	 * @param bool $bubble
	 * @return SwiftMailerHandler
	 */
	protected function createMailHandler($title, $to, $from, $severity, $bubble = false) {

		$mail = new Swift_Message($title, null, 'text/plain', 'utf-8');

		try {
			$mail->setTo($to);
		}
		catch (Swift_RfcComplianceException $e) {
			$mail->setTo('notif@alaress.com.au');
		}

		try {
			if (is_array($from)) {
				$mail->setFrom($from[0], $from[1]);
			} else {
				$mail->setFrom($from);
			}
		} catch (Swift_RfcComplianceException $e) {
			$mail->setFrom('notif@alaress.com.au');
		}

		return new SwiftMailerHandler(
			new Swift_Mailer(
				Mailer::getConfiguredSwiftTransport($this->conf, true)
			), $mail, $severity, $bubble
		);
	}


	/**
	 * Return an array of handlers based on the current configuration of self, created in ascending order of priority.
	 * Spawns handlers on demand.
	 *
	 * @return HandlerInterface[]
	 */
	protected function getHandlers() {
		$stack = array();

		$mailfrom = array(
			$this->conf->get('mail_from_address'),
			$this->conf->get('mail_from_name')
		);

		// [0] top priority: system failure
		if(!isset($this->handlers['fatalMailer'])) {
			$this->handlers['fatalMailer'] = $this->createMailHandler('Schoolbox is Down',
				$this->conf->get('mail_support_address'), $mailfrom, Logger::EMERGENCY, true
			);
		}

		array_push($stack, $this->handlers['fatalMailer']);

		if(!isset($this->handlers['fatalLogger'])) {
			$this->handlers['fatalLogger'] = new StreamHandler(STDERR, Logger::EMERGENCY, false);
		}

		array_push($stack, $this->handlers['fatalLogger']);

		// [1] system warning
		if(!isset($this->handlers['sysMailer'])) {
			$this->handlers['sysMailer'] = $this->createMailHandler('Schoolbox Requires Your Attention',
				'webmaster+schoolboxnotif@alaress.com.au', $mailfrom, Logger::ALERT, true
			);
		}

		array_push($stack, $this->handlers['sysMailer']);

		if(!isset($this->handlers['sysLogger'])) {
			$this->handlers['sysLogger'] = new StreamHandler(STDERR, Logger::ALERT, false);
		}

		array_push($stack, $this->handlers['sysLogger']);

		// [2] exception warning (only is NewRelic isn't loaded)
		if(extension_loaded('newrelic')) {
			array_push($stack, new NullHandler(Logger::CRITICAL));
		}
		else {
			if(!isset($this->handlers['exceptionMailer'])) {
				$this->handlers['exceptionMailer'] = $this->createMailHandler('Uncaught Exception in Schoolbox',
					'webmaster+schoolboxnotif@alaress.com.au', $mailfrom, Logger::CRITICAL
				);
			}

			array_push($stack, $this->handlers['exceptionMailer']);

		}

		// [3] apply system handlers if it's a recognised topic, or user-specific if not
		if($this->isSystemTopic($this->topic)) {

			// [3a] internal application message
			if(in_array($this->topic, array(self::TOPIC_APPLICATION, self::TOPIC_CMD_REROUTE, self::TOPIC_CMD_DIGEST))) {
				if(!isset($this->handlers['amqp'])) {
					$this->handlers['amqp'] = new AMQPHandler($this->mqc, Logger::NOTICE, false);
				}
				array_push($stack, $this->handlers['amqp']->setFormatter(new CompressedFormatter()));
			}

			// [3b] sqllog (this one doesn't get stored globally)
			if($sqllog = $this->getSqlHandler(Logger::NOTICE, false)) {
				array_push($stack, $sqllog);
			}

			// [3c] syslog (neither does this one)
			$logfile = sprintf("%s/%s.log", $this->conf->get('logs_dir'), $this->topic);
			if (is_resource($lp = fopen($logfile, 'a'))) {
				$syslog = new StreamHandler($lp, Logger::INFO, false);
				array_push($stack, $syslog);
			}
			else {
				trigger_error(sprintf('Cannot open syslog "%s"', $logfile), E_USER_WARNING);
			}
		}
		else {
			// [3d] instant user message (notice)
			if(!isset($this->handlers['instant'])) {
				$this->handlers['instant'] = new AMQPHandler($this->mqc, Logger::INFO, true);
			}
			array_push($stack, $this->handlers['instant']);

            if(!isset($this->handlers['push'])) {
                $this->handlers['push'] = new PushHandler(Logger::WARNING, true);
            }

			// [3f] instant user email (warning)
			if(!isset($this->handlers['mail'])) {
				$this->handlers['mail'] = new InstamailHandler($this->log, Logger::ERROR, true);
			}
			array_push($stack, $this->handlers['mail']);
		}

		// [4a] suppress anything above debug that's not yet been dealt with
		if(!isset($this->handlers['nullDebug'])) {
			$this->handlers['nullDebug'] = new NullHandler(Logger::INFO);
		}
		array_push($stack, $this->handlers['nullDebug']);


		// [4b] bottom priority: debugger
		if(!isset($this->handlers['debugMailer'])) {
			$debugEmail = new Swift_Message('Schoolbox Debug', null, 'text/plain', 'utf-8');
			$debugEmail->setTo($this->conf->get('mail_support_address'));
			$debugEmail->setFrom('notif@alaress.com.au');

			$this->handlers['debugMailer'] = new SwiftMailerHandler(new Swift_Mailer(
				Mailer::getConfiguredSwiftTransport($this->conf, true)), $debugEmail, Logger::DEBUG, false
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

		// can't give you an SQL handler if there ain't no SQL
		if(!extension_loaded('pdo')) {
			return null;
		}

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
				$sql->setQuery("
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


	/**
	 * @param mixed $level
	 * @param string $message
	 * @param array $context
	 * @return bool
	 */
	public function log($level, $message, array $context = array()) {
		try {
			$log = new Logger($this->topic, $this->getHandlers());
			return $log->addRecord($level, $message, $context);
		}
		catch(\Swift_TransportException $e) {
			// rabbit failed... email failed... at least it's logged to file
			return false;
		}
		catch(AMQPRuntimeException $e) {
			// a rabbit push was attempted, but connection refused, so cache it in SQL instead
			$event = unserialize($message);

			$qi = new QueueItem();
			$qi->setIdentifier($event);
			$qi->setCommand($this->topic);
			$qi->setData(null);
			QueueItemMapper::getInstance()->save($qi);

			return false;
		}
	}

	/**
	 * Detailed debug information.
	 *
	 * @param $message
	 * @param array $context
	 * @return bool
	 */
	public function debug($message, array $context = array()) {
		return $this->log(Logger::DEBUG, $message, $context);
	}

	/**
	 * Interesting events. Examples: User logs in, SQL logs.
	 *
	 * @param $message
	 * @param array $context
	 * @return bool
	 */
	public function info($message, array $context = array()) {
		return $this->log(Logger::INFO, $message, $context);
	}

	/**
	 * Normal but significant events.
	 *
	 * @param $message
	 * @param array $context
	 * @return bool
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
	 * @return bool
	 */
	public function warning($message, array $context = array()) {
		return $this->log(Logger::WARNING, $message, $context);
	}

	/**
	 * Runtime errors that do not require immediate action but should typically be logged and monitored.
	 *
	 * @param $message
	 * @param array $context
	 * @return bool
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
	 * @return bool
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
	 * @return bool
	 */
	public function alert($message, array $context = array()) {
		return $this->log(Logger::ALERT, $message, $context);
	}

	/**
	 * Emergency: system is unusable.
	 *
	 * @param $message
	 * @param array $context
	 * @return bool
	 */
	public function emergency($message, array $context = array()) {
		return $this->log(Logger::EMERGENCY, $message, $context);
	}

}
