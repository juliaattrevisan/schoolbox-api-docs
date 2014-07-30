<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\AMQP\Bunny;


interface ControlInterface {

	/**
	 * @param array $fields
	 * @param string $vhost
	 * @return array
	 */
	public function listQueues($fields, $vhost = '');

	/**
	 * @param array $fields
	 * @param string $vhost
	 * @return array
	 */
	public function listExchanges($fields, $vhost = '');

	/**
	 * @param array $fields
	 * @param string $vhost
	 * @return array
	 */
	public function listBindings($fields, $vhost = '');

	/**
	 * @param array $fields
	 * @return array
	 */
	public function listConnections($fields);

	/**
	 * @param array $fields
	 * @return array
	 */
	public function listChannels($fields);

	/**
	 * @param string $vhost
	 * @return array
	 */
	public function listConsumers($vhost = '');

}