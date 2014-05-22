<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Handler;


use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use PhpAmqpLib\Channel\AMQPChannel;
use Schoolbox\Notif\MQ\Connector as MQConnector;
use Schoolbox\Notif\NotifException;

class AMQPHandler extends AbstractProcessingHandler {

	/** @var MQConnector */
	protected $connection;

	/**
	 * @param MQConnector $connection
	 * @param bool|int $level
	 * @param bool $bubble
	 * @throws \Schoolbox\Notif\NotifException
	 */
	public function __construct($connection, $level = Logger::DEBUG, $bubble = true) {
		parent::__construct($level, $bubble);

		if($connection instanceof MQConnector) {
			$this->connection = $connection;
		}
		else {
			throw new NotifException("connection not instance of Schoolbox\\Notif\\MQ\\Connector");
		}

		// by default, this uses a JSON formatter
		$this->setFormatter(new JsonFormatter());
	}


	/**
	 * @return MQConnector
	 */
	public function getMQConnector() {
		return $this->connection;
	}


	/**
	 * @return AMQPChannel
	 */
	public function getChannel() {
		return $this->connection->getChannel();
	}

	/**
	 * Writes the record down to the log of the implementing handler
	 *
	 * @param  array $record
	 * @return void
	 */
	protected function write(array $record) {

		// key in the form of [severity].[namespace](.[namespace])*
		$routingKey = sprintf(
			'%s.%s',
			substr($record['level_name'], 0, 4),
			$record['channel'] ?: 'system'
		);

		$this->connection->publish($record["formatted"], $routingKey);

	}



} 