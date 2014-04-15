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

class CommentMapper extends Mapper {

	/**
	 * @return void
	 */
	protected function mapping() {
		$this->hasTable('blogComment');
		$this->hasModel('\\Schoolbox\\Blog\\Comment');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'bid',
			'property'   => 'blog',
			'type'       => self::TYPE_MODEL,
			'join'       => 'blog'
		));

		$this->hasMap(array(
			'column'     => 'owner',
			'property'   => 'owner',
			'type'       => self::TYPE_MODEL,
			'join'       => 'owner'
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
			'column'     => 'body',
			'property'   => 'body',
			'type'       => self::TYPE_STRING
		));

		$this->hasJoin('blog', array(
			'mapper'    => '\\Schoolbox\\Blog\\BlogMapper',
			'this_key'  => 'bid',
			'other_key' => 'id',
		));

		$this->hasJoin('owner', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'owner',
			'other_key' => 'id',
		));

		$this->hasSoftDeleteDateField('deleted');
	}
}