<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Message;


use Schoolbox\Mapper;

class NSMapper extends Mapper {

	protected function mapping() {
		$this->hasTable('msg_namespace');
		$this->hasModel('\\Schoolbox\\Realtime\\Message\\NS');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'slug',
			'property'   => 'slug',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'label',
			'property'   => 'label',
			'type'       => self::TYPE_STRING,
		));
	}

}