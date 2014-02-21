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
use Schoolbox\ConfigLoader;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\MappingCollection;

class ConfigMapper extends Mapper implements IConfigLoader {

	public function __construct() {
		$this->database = \Database::getInstance();
		$this->map = new MappingCollection();
		$this->setup();
		$this->setConfigLoader($this); // this line will possibly blow up in my face
		$this->setColumnFactory(new ColumnFactory());
		$this->mapping();
	}


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
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));

	}


	public function get($var) {
		$coll = $this->findByColumn('name', $var);
		if($coll->count()) {
			return $coll->first()->getValue();
		}
		return '';
	}


}