<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Persistence;


use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Schedule\Event;

class QueueItemMapper extends Mapper {

	/**
	 * @var IConfigLoader
	 */
	protected $config_loader;


	/**
	 * @param  IConfigLoader $config_loader
	 *
	 * @return $this
	 */
	public function setConfigLoader($config_loader) {
		$this->config_loader = $config_loader;
		return $this;
	}


	/**
	 * @return IConfigLoader
	 */
	public function getConfigLoader() {
		return $this->config_loader;
	}


	/**
	 * Sets up the config loader in this object.
	 */
	public function setup() {
		parent::setup();
		$this->setConfigLoader(ConfigMapper::getInstance());
	}


	protected function mapping () {
		$this->hasTable('scheduleq');
		$this->hasModel('\\Schoolbox\\Realtime\\Schedule\\Persistence\\QueueItem');

		// note that this is a dynamic property that does not exist in the model (but it does in the DB)
		$this->hasMap(array(
			'column'     => 'id_hash',
			'property'   => 'hash',
			'type'       => self::TYPE_STRING,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'identifier',
			'property'   => 'identifier',
			'type'       => self::TYPE_SERIALIZED,
		));
		$this->hasMap(array(
			'column'     => 'command',
			'property'   => 'command',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'data',
			'property'   => 'data',
			'type'       => self::TYPE_SERIALIZED,
		));
	}


	/**
	 * This mapper does not raise notifications.
	 *
	 * @param object $object
	 * @param string $verb
	 * @param null   $previous
	 * @return bool
	 */
	protected function raiseNotification($object, $verb, $previous = null) {
		return false;
	}

}
