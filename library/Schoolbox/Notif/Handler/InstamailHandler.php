<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Handler;
use Monolog\Handler\SwiftMailerHandler;
use Monolog\Logger;


/**
 * Handler for sending instant emails
 *
 * Class InstamailHandler
 * @package Schoolbox\Notif\Handler
 */
class InstamailHandler extends SwiftMailerHandler {

	public function __construct($connection, $level = Logger::DEBUG, $bubble = true) {
		parent::__construct($connection, $level, $bubble);
	}


	/**
	 * Writes the record down to the log of the implementing handler
	 *
	 * @param  array $record
	 * @return void
	 */
	protected function write(array $record) {

		$routingKey = sprintf(
			'DIGE.%s',
			$record['channel'] ?: 'system'
		);

		$this->connection->publish($record["formatted"], $routingKey);
	}


} 