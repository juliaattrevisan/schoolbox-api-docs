<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Handler;


use Alaress\Async\AMQP\AMQPInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Schoolbox\Notif\NotifException;

class AMQPHandler extends AbstractProcessingHandler {

	/** @var AMQPInterface */
	protected $connection;

	/**
	 * @param AMQPInterface $connection
	 * @param bool|int $level
	 * @param bool $bubble
	 * @throws NotifException
	 */
	public function __construct($connection, $level = Logger::DEBUG, $bubble = true) {
		parent::__construct($level, $bubble);

		// by default, this uses a JSON formatter
		$this->setFormatter(new JsonFormatter());
		$this->connection = $connection;
	}


	/**
	 * @return AMQPInterface
	 */
	public function getMQConnector() {
		return $this->connection;
	}


	/**
	 * Writes the record down to the log of the implementing handler
	 *
	 * @param  array $record
	 * @throws AMQPRuntimeException
	 * @return void
	 */
	protected function write(array $record) {
		if(!$this->getMQConnector() instanceof AMQPInterface) {
			throw new AMQPRuntimeException("Not connected to AMQP, cannot write");
		}
		// key in the form of [severity].[namespace](.[namespace])*
		$routingKey = sprintf(
			'%s.%s',
			substr($record['level_name'], 0, 4),
			$record['channel'] ?: 'system'
		);

        $message = json_decode($record['message'], true);

        $properties = array();

        if (isset($message['meta']['ttl'])) {
            $properties['expiration'] = $message['meta']['ttl'] * 1000;

        } elseif ($message['namespace'] == 'ALERT') {
            $properties['expiration'] = 30000; // 30 seconds

        }

		$this->connection->publish($record["formatted"], $routingKey, $properties);

	}



} 