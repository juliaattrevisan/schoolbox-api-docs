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
use PhpAmqpLib\Connection\AMQPConnection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Message\External\TargetedMessage;

class Connector extends AMQPConnector {

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
				$this->conf->get('amqp_pass')
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
		// attempt to extract the message body, so as to efficiently de-dupe resent messages
		if($data = json_decode($message, true)) {
			$string = ($data['message']);
			if($obj = unserialize($string)) {
				return self::deriveMessageId($obj, $channelId);
			}
		}

		// cast null to 0
		$bits = array(is_null($channelId) ? 0 : $channelId);
		$bits[] = $message;
		return sha1(implode('.', $bits));
	}


	/**
	 * @param TargetedMessage $msg
	 * @param int $channelId
	 * @return string
	 */
	public static function deriveMessageId($msg, $channelId = null) {
		$body = $msg->getBody()->toArray();
		$bits = array($channelId);
		$bits[] = $body['body'].$body['href'];
		return sha1(implode('.', $bits));
	}
} 