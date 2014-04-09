<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog;


use Schoolbox\Mapper;

class BlogMapper extends Mapper {

	protected function mapping() {
		$this->hasTable('blog');
		$this->hasModel('\Schoolbox\Blog');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'cid',
			'property'   => 'category',
			'type'       => self::TYPE_MODEL,
			'join'       => 'category'
		));

		$this->hasMap(array(
			'column'     => 'owner',
			'property'   => 'owner',
			'type'       => self::TYPE_MODEL,
			'join'       => 'user'
		));

		$this->hasMap(array(
			'column'     => 'created',
			'property'   => 'created',
			'type'       => self::TYPE_DATETIME
		));

		$this->hasMap(array(
			'column'     => 'deleted',
			'property'   => 'deleted',
			'type'       => self::TYPE_DATETIME
		));

		$this->hasMap(array(
			'column'     => 'heading',
			'property'   => 'heading',
			'type'       => self::TYPE_STRING
		));

		$this->hasMap(array(
			'column'     => 'body',
			'property'   => 'body',
			'type'       => self::TYPE_STRING
		));


		$this->hasJoin('category', array(
			'mapper'    => '\Schoolbox\Resource\CategoryMapper',
			'this_key'  => 'cid',
			'other_key' => 'id'
		));

		$this->hasJoin('user', array(
			'mapper'    => '\Schoolbox\UserMapper',
			'this_key'  => 'owner',
			'other_key' => 'id'
		));

		$this->hasSoftDeleteDateField('deleted');
	}

} 