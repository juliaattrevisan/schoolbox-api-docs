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

class TextboxMapper extends Mapper {

	protected function mapping() {
		$this->hasTable('textBox');
		$this->hasModel('\\Schoolbox\\Resource\\Textbox');

		$this->hasMap(array(
			'column'     => 'textBoxID',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'categoryID',
			'property'   => 'category',
			'type'       => self::TYPE_MODEL,
			'join'       => 'category'
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

		$this->hasMap(array(
			'column'     => 'deleted',
			'property'   => 'deleted',
			'type'       => self::TYPE_BOOL_ENUM
		));

		$this->hasJoin('category', array(
			'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
			'this_key'  => 'categoryID',
			'other_key' => 'id'
		));

        /* Took one on the chin for samuel
         */
		$this->hasSoftDeleteField('deleted', 'true', 'false');
	}
}