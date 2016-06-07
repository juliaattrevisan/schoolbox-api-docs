<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\MQ;


use Alaress\Async\AMQP\AMQPConnector;
use Alaress\Async\AMQP\AMQPExchange;
use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\Notification\Instance\Instance;
use InvalidArgumentException;
use PhpAmqpLib\Connection\AMQPConnection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Message\External\TargetedConverter;

class Connector extends AMQPConnector {

	/** @var AMQPInterface */
	protected static $instance;

	/**
	 * @return Connector
	 */
	public static function getInstance() {
		if(!self::$instance instanceof self) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	const PRIMARY_EXCHANGE = 'sb.%s.tx';
	const PRIMARY_TYPE     = 'topic';

	const SECONDARY_NAME   = 'sb.%s.ux.%s';
	const SECONDARY_TYPE   = 'topic';

	/** @var IConfigLoader */
	protected $conf;


	/**
	 * @param array $settings
	 */
	public function __construct($settings = array()) {
		parent::__construct($settings);
		$this->conf = ConfigMapper::getInstance();
		$this->connect();
	}


	/**
	 * Establishes a new AMQP connection if it doesn't already exist.
	 * @return void
	 */
	protected function connect() {
		if(!$this->conn instanceof AMQPConnection) {
			$this->conn = new AMQPConnection(
				$this->conf->get('amqp_host'),
				$this->conf->get('amqp_port'),
				$this->conf->get('amqp_user'),
				$this->conf->get('amqp_pass'),
                $this->conf->get('amqp_vhost')
			);
		}
	}


	/**
	 * @return AMQPExchange
	 */
	public function getPrimaryExchange() {
		return new AMQPExchange(sprintf(self::PRIMARY_EXCHANGE, INSTANCE_ID), self::PRIMARY_TYPE);
	}


	/**
	 * @param string $name
	 * @return AMQPExchange
	 */
	public function getSecondaryExchange($name) {
		return new AMQPExchange(sprintf(self::SECONDARY_NAME, INSTANCE_ID, $name), self::SECONDARY_TYPE);
	}


	/**
	 * Accepts a string representation of a message and calculates a hash for it
	 *
	 * @param string $message
	 * @param string $channelId
	 * @return string
	 */
	protected function hashMessage($message, $channelId) {
		$instance = null;

		// attempt to extract the message body, so as to efficiently de-dupe resent messages
		if($data = json_decode($message, true)) {
			try {
				$instance = Instance::jsonUnserialize($data['message']);
			}
			catch(InvalidArgumentException $e) {
				// TODO once TargetedMessage has been fully deprecated, this catch can be removed
				$conv = new TargetedConverter();
				try {
					$instance = $conv->convert($data['message']);
				}
				catch(InvalidArgumentException $e) {
					// that just failed completely
				}
			}
			if($instance instanceof Instance) {
				return $instance->getBaseHash();
			}
		}

		// couldn't decode, so let's just hash what we got
		$bits = array(is_null($channelId) ? 0 : $channelId);
		$bits[] = $message;
		return sha1(implode('.', $bits));
	}

}
