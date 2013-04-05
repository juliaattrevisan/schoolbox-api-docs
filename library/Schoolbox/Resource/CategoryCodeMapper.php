<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 

namespace Schoolbox\Resource;
use Schoolbox\Mapper;


class CategoryCodeMapper extends Mapper {

	protected function mapping() {
		$this->hasModel('\\Schoolbox\\Resource\\CategoryCode');
		$this->hasTable('categoryCode');
		$this->hasMap(array(
			'column'     => 'categoryID',
			'property'   => 'category',
			'type'       => self::TYPE_MODEL,
			'join'       => 'category',
		));
		$this->hasMap(array(
			'column'     => 'code',
			'property'   => 'code',
			'type'       => self::TYPE_STRING,
		));
		$this->hasJoin('category', array(
			'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
			'this_key'  => 'category',
			'other_key' => 'id',
		));
	}
}