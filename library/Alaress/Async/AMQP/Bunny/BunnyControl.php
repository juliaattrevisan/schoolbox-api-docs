<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\AMQP\Bunny;


use RuntimeException;

class BunnyControl implements ControlInterface {

	/** @var string */
	protected $node;

	/** @var bool */
	protected $quietMode;


	/**
	 * @param string $node
	 * @param bool   $quietMode
	 */
	public function __construct($node = '', $quietMode = true) {
		$this->node = $node;
		$this->quietMode = $quietMode;
	}


	/**
	 * @param string $cmd
	 * @throws RuntimeException
	 * @return array
	 */
	protected function exec($cmd) {
		// there's a bit of black magic in here
		if(posix_getuid() !== 0) {
			// this method can only succeed if the user is root - don't even try otherwise
			throw new RuntimeException("rabbitmqctl can only be run by root");
		}

		$flags = $this->quietMode ? '-q ' : '';
		$flags .= $this->node ? "-n {$this->node} " : '';

		exec("/usr/sbin/rabbitmqctl $flags $cmd", $output);

		return $output;
	}


	/**
	 * Map the given fields to the output returned from exec()
	 *
	 * @param array $output
	 * @param array $fields
	 * @return array
	 */
	protected function parse($output, $fields) {
		if(!count($output)) {
			return array();
		}

		$fieldCount = count($fields);

		$result = array();

		foreach($output as $line) {
			$xpl = preg_split('/\s+/', $line);

			if(count($xpl) != $fieldCount) {
				continue;
			}

			$result[] = array_combine($fields, $xpl);
		}

		return $result;
	}


	/**
	 * Generic implementation of list*() methods
	 *
	 * @param string $type
	 * @param array  $opts
	 * @param array  $fields
	 * @param string $vhost
	 * @return array
	 */
	protected function retrieve($type, $opts, $fields, $vhost = '') {
		foreach(array_diff($fields, $opts) as $invalid) {
			unset($fields[$invalid]);
		}

		// no fields left?
		if(!count($fields)) {
			return array();
		}

		$cmd = "list_$type ";
		$cmd .= $vhost ? "-p $vhost" : '';
		$cmd .= implode(' ', $fields);

		$output = $this->exec($cmd);
		return $this->parse($output, $fields);
	}

	/**
	 * @param array  $fields
	 * @param string $vhost
	 * @return array
	 */
	public function listQueues($fields, $vhost = '') {
		$opts = array(
			'name', 'durable', 'auto_delete', 'arguments', 'policy', 'pid', 'owner_pid',
			'exclusive_consumer_pid', 'exclusive_consumer_tag', 'messages_ready',
			'messages_unacknowledged', 'messages', 'consumers', 'consumer_utilisation',
			'memory', 'slave_pids', 'synchronised_slave_pids', 'status'
		);

		return $this->retrieve('queues', $opts, $fields);
	}


	/**
	 * @param array  $fields
	 * @param string $vhost
	 * @return array
	 */
	public function listExchanges($fields, $vhost = '') {
		$opts = array(
			'name', 'type', 'durable', 'auto_delete', 'internal', 'arguments', 'policy'
		);

		return $this->retrieve('exchanges', $opts, $fields);
	}


	/**
	 * @param array  $fields
	 * @param string $vhost
	 * @return array
	 */
	public function listBindings($fields, $vhost = '') {
		$opts = array(
			'source_name', 'source_kind', 'destination_name', 'destination_kind',
			'routing_key', 'arguments'
		);

		return $this->retrieve('bindings', $opts, $fields);
	}


	/**
	 * @param array $fields
	 * @return array
	 */
	public function listConnections($fields) {
		// not implemented
		return array();
	}


	/**
	 * @param array $fields
	 * @return array
	 */
	public function listChannels($fields) {
		// not implemented
		return array();
	}


	/**
	 * @param string $vhost
	 * @return array
	 */
	public function listConsumers($vhost = '') {
		// not implemented
		return array();
	}

}