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
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class AMQPHandler extends AbstractProcessingHandler {

	const EXCHANGE = 'topic_logs';
	const TYPE = 'topic';

	protected $connection;

	/**
	 * @param AMQPStreamConnection $connection
	 * @param bool|int $level
	 * @param bool $bubble
	 */
	function __construct($connection, $level = Logger::DEBUG, $bubble = true) {
		parent::__construct($level, $bubble);
		$this->connection = $connection;
		$this->connection->channel()->exchange_declare(self::EXCHANGE, self::TYPE, false, false, false);
	}

	/**
	 * Writes the record down to the log of the implementing handler
	 *
	 * @param  array $record
	 * @return void
	 */
	protected function write(array $record) {

		$msg = new AMQPMessage($record["formatted"]);

		// key in the form of [severity].[namespace](.[namespace])*
		$routingKey = sprintf(
			'%s.%s',
			substr($record['level_name'], 0, 4),
			$record['channel'] ?: 'system'
		);

		$this->connection->channel()->basic_publish($msg, self::EXCHANGE, $routingKey);

	}



} 