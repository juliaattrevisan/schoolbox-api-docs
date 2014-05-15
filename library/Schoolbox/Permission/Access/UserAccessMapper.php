<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Permission\Access;


use Schoolbox\Mapper;

class UserAccessMapper extends Mapper {

	/**
	 * @return void
	 */
	protected function mapping() {
		$this->hasTable('userAccess');
		$this->hasModel('\\Schoolbox\\Permission\\Access\\UserAccess');

		$this->hasMap(array(
			'column'     => 'uid',
			'property'   => 'user',
			'type'       => self::TYPE_MODEL,
			'identifier' => true,
			'join'       => 'user'
		));

		$this->hasMap(array(
			'column'     => 'service',
			'property'   => 'service',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'sid',
			'property'   => 'serviceIdentifier',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'type',
			'property'   => 'type',
			'type'       => self::TYPE_INTEGER
		));

		$this->hasJoin('user', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'uid',
			'other_key' => 'id'
		));

	}

} 