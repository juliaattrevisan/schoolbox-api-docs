<?php
/**
 * Mapper for category log entries.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Resource;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\Resource\Category;

class CategoryLogMapper extends Mapper {

	protected function mapping() {
		$this->hasModel('\\Schoolbox\\Resource\\CategoryLog');
		$this->hasTable('homepage_log');
		$this->hasMap(array(
			'column'   => 'cid',
			'property' => 'category',
			'type'     => self::TYPE_MODEL,
			'join'     => 'category',
		));
		$this->hasMap(array(
			'column'   => 'user',
			'property' => 'user',
			'type'     => self::TYPE_MODEL,
			'join'     => 'user',
		));
		$this->hasMap(array(
			'column'            => 'datetime',
			'property'          => 'lastAccessTime',
			'type'              => self::TYPE_DATETIME,
			'updated_timestamp' => true,
		));
		$this->hasMap(array(
			'column'   => 'hitcount',
			'property' => 'hitcount',
			'type'     => self::TYPE_INTEGER,
		));
		$this->hasJoin('category', array(
			'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
			'this_key'  => 'cid',
			'other_key' => 'id',
		));
		$this->hasJoin('user', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'user',
			'other_key' => 'id',
		));
	}

	/**
	 * Override of getUpdateString() to only ever increment the hit count, and
	 * to update the last visit time to the current time.
	 *
	 * @return String
	 */
	public function getUpdateString() {
		return '`hitcount` = `hitcount` + 1, `datetime` = NOW()';
	}

	/**
	 * Increments hit count for this user and category.
	 *
	 * @param int|User     $user     A User object or user ID
	 * @param int|Category $category A Category object or ID
	 */
	public function incrementHitcount($user, $category) {
		$item = new CategoryLog();
		$item->setUser($user)->setCategory($category)->setHitcount(1);
		$this->save($item);
	}
}