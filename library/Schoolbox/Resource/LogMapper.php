<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource;


use Schoolbox\Mapper;

class LogMapper extends Mapper {

	const ACTION_CREATE = 'create';
	const ACTION_READ   = 'access';
	const ACTION_UPDATE = 'modify';
	const ACTION_DELETE = 'delete';

	const KIND_FILE     = 'file';
	const KIND_FOLDER   = 'folder';
	const KIND_LINK     = 'link';
	const KIND_MYFILE   = 'myfile';
	const KIND_MYFOLDER = 'myfolder';

	/**
	 * @return void
	 */
	protected function mapping() {
		$this->hasTable('cmsLog');
		$this->hasModel('\\Schoolbox\Resource\\Log');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'object',
			'property'   => 'objectKind',
			'type'       => self::TYPE_STRING
		));

		$this->hasMap(array(
			'column'     => 'objectId',
			'property'   => 'objectId',
			'type'       => self::TYPE_INTEGER
		));

		$this->hasMap(array(
			'column'     => 'action',
			'property'   => 'action',
			'type'       => self::TYPE_STRING
		));

		$this->hasMap(array(
			'column'     => 'user',
			'property'   => 'user',
			'type'       => self::TYPE_MODEL
		));

		$this->hasMap(array(
			'column'            => 'time',
			'property'          => 'timestamp',
			'type'              => self::TYPE_TIMESTAMP,
			'created_timestamp' => true
		));

		$this->hasJoin('user', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'user',
			'other_key' => 'id'
		));

	}



} 