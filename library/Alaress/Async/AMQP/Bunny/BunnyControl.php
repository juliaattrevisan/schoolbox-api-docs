<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\AMQP\Bunny;


use ReflectionProperty;
use RuntimeException;

/**
 * Class BunnyControl
 *
 * This class is a PHP wrapper around `rabbitmqadmin`
 *
 * @package Alaress\Async\AMQP\Bunny
 */
class BunnyControl implements ControlInterface
{

    /** @var string - configuration file [default: ~/.rabbitmqadmin.conf] */
    protected $config;

    /** @var string - node described in the configuration file [default: 'default' only if configuration file is specified] */
    protected $node;

    /** @var string - connect to host HOST [default: localhost] */
    protected $host;

    /** @var string - connect to port PORT [default: 15672] */
    protected $port;

    /** @var string - connect to vhost VHOST [default: all vhosts for list, '/' for declare] */
    protected $vhost;

    /** @var string - connect using username USERNAME [default: guest] */
    protected $username;

    /** @var string - connect using password PASSWORD [default: guest] */
    protected $password;

    /** @var bool - suppress status messages [default: True] */
    protected $quiet;

    /** @var bool - connect with ssl [default: False] */
    protected $ssl;

    /** @var string - PEM format key file for SSL */
    protected $sslKeyFile;

    /** @var string - PEM format certificate file for SSL */
    protected $sslCertFile;

    /** @var string - format for listing commands - one of [raw_json, long, pretty_json, kvp, tsv, table, bash] [default: table] */
    protected $format = 'tsv';

    /** @var string - sort key for listing queries */
    protected $sort;

    /** @var string - reverse the sort order */
    protected $sortReverse;

    /** @var string - maximum depth to recurse for listing tables [default: 1] */
    protected $depth;


    /**
     * This constructor accepts config arguments the same as the `rabbitmqadmin` on commandline
     * Refer to `rabbitmqadmin --help` for valid values (note keys are dash-separated names rather than camelCased).
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        foreach ($config as $key => $value) {

            $property = score2camel($key, '-');

            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }
    }


    /**
     * Uses reflection to retrieve the values of the properties
     *
     * @return array
     */
    protected function getFlags()
    {
        $rc = new \ReflectionClass($this);

        $props = $rc->getProperties(ReflectionProperty::IS_PROTECTED);

        $args = [];

        foreach ($props as $prop) {

            $name  = $prop->getName();
            $value = $this->$name;

            if ($value === null) {
                continue;
            }

            $args[camel2score($name, '-')] = $value;
        }

        return $args;
    }


    /**
     * Converts the argument array to a CLI argument string
     *
     * @return string
     */
    protected function getFlagString()
    {
        $args = $this->getFlags();

        $mapped = array_map(
            function ($key, $value) {
                if ($value === true) {
                    return "--$key";
                }

                return "--$key=$value";
            },
            array_keys($args), array_values($args)
        );

        return implode(' ', $mapped);
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

        $flags = $this->getFlagString();

		exec("/usr/local/bin/rabbitmqadmin $flags $cmd", $output);

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

        // in the default format (tsv) there is a header row we need to remove
        array_shift($output);

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
	 * @return array
	 */
	protected function retrieve($type, $opts, $fields) {
		foreach(array_diff($fields, $opts) as $invalid) {
			unset($fields[$invalid]);
		}

		// no fields left?
		if(!count($fields)) {
			return array();
		}

		$cmd = "list $type ";
		$cmd .= implode(' ', $fields);

		$output = $this->exec($cmd);
		return $this->parse($output, $fields);
	}

	/**
	 * @param array  $fields
	 * @return array
	 */
	public function listQueues($fields) {
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
	 * @return array
	 */
	public function listExchanges($fields) {
		$opts = array(
			'name', 'type', 'durable', 'auto_delete', 'internal', 'arguments', 'policy'
		);

		return $this->retrieve('exchanges', $opts, $fields);
	}


	/**
	 * @param array  $fields
	 * @return array
	 */
	public function listBindings($fields) {
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
	 * @return array
	 */
	public function listConsumers() {
		// not implemented
		return array();
	}

}