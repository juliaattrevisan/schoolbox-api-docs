<?php
/**
 * Test mapper.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Test;
use Schoolbox\Mapper;

class TestMapper extends Mapper {
	protected function mapping() {
		$this->hasModel('\\Schoolbox\\Test\\TestModel');
		$this->hasMap(array(
			'column'     => 'storage_id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'filename',
			'property'   => 'filename',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'is_viewed',
			'property'   => 'isViewed',
			'type'       => self::TYPE_BOOLEAN,
		));
		$this->hasMap(array(
			'column'     => 'file',
			'property'   => 'file',
			'type'       => self::TYPE_LOB,
		));
		$this->hasMap(array(
			'column'     => 'parent',
			'property'   => 'parent',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasJoin('parent', array(
			'mapper'    => '\\Schoolbox\\Test\\TestMapper',
			'this_key'  => 'category',
			'other_key' => 'id',
		));
	}
}