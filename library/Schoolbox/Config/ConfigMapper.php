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


	/**
	 * Build the Mapper
	 */
	public function __construct() {
		$this->database = \Database::getInstance();
		$this->map = new MappingCollection();
		$this->setup();
		$this->setConfigLoader($this); // this line will possibly blow up in my face
		$this->setColumnFactory(new ColumnFactory());
		$this->mapping();
	}


	/**
	 * @return void
	 */
	protected function mapping() {
		$this->hasTable('config');
		$this->hasModel('\\Schoolbox\\Config\\Config');

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
		if(!isset($this->cache[$var])) {
			$coll = $this->findByColumn('name', $var);
			if($coll->count()) {
				$this->cache[$var] = $coll->first()->getValue();
			}
		}
		return $this->cache[$var];
	}


}
