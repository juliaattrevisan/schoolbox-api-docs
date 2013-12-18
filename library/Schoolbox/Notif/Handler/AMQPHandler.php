<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Handler;


use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Schoolbox\Notif\MQ\Connector as MQConnector;

class AMQPHandler extends AbstractProcessingHandler {

	protected $connection;

	/**
	 * @param MQConnector $connection
	 * @param bool|int $level
	 * @param bool $bubble
	 */
	public function __construct($connection, $level = Logger::DEBUG, $bubble = true) {
		parent::__construct($level, $bubble);
		$this->connection = $connection;
	}

	public function getMQConnector() {
		return $this->connection;
	}

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