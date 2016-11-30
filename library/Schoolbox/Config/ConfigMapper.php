<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Config;


use Schoolbox\Column\Factory as ColumnFactory;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\MappingCollection;

class ConfigMapper extends Mapper implements IConfigLoader {

	/** @var array */
	protected $cache = array();

	/** @var array */
	protected $consts;

	/**
	 * Build the Mapper
	 */
	public function __construct() {
		// Load database only on request, so this can be tested
		$this->map = new MappingCollection();
		$this->setup();
		$this->setConfigLoader($this); // this line will possibly blow up in my face
		$this->setColumnFactory(new ColumnFactory());

		$w = realpath( __DIR__ . "/../../..");
		$this->consts = array(
			'siteRoot' => $w,
			'webRoot'  => "$w/www",
			'webBase'  => ''
		);

		$this->mapping();
	}


	/**
	 * @return void
	 */
	protected function mapping() {
		$this->hasTable('config');
		$this->hasModel(Config::class);

		$this->hasMap(array(
			'column'     => 'configID',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));

		$this->hasMap(array(
			'column'     => 'name',
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'value',
			'property'   => 'value',
			'type'       => self::TYPE_STRING,
		));

	}


	/**
	 * Get a config variable
	 *
	 * @param string $var
	 * @return mixed
	 */
	public function get($var) {
		if(isset($this->consts[$var])) {
			return $this->consts[$var];
		}
		elseif(defined($var)) {
			return constant($var);
		}
		if (empty($this->cache)) {
			$this->loadCache();
		}
		if(isset($this->cache[$var])) {
			return $this->cache[$var];
		}
		return null;
	}

	/**
	 * Load entire cache at once, not just one result at a time
	 */
	protected function loadCache()
	{
		// Load database on request only
		if (!($this->database instanceof \Database)) {
			$this->database = \Database::getInstance();
		}
		// Perform this common action without the overhead of hydration
		foreach ($this->database->query("SELECT `name`, `value` FROM `{$this->getTable()}`") as $row) {
			$this->cache[$row['name']] = $row['value'];
		}
	}


	/**
	 * Drop any cached config
	 *
	 * @return void
	 */
	public function dropCache() {
		$this->cache = array();
	}

}
