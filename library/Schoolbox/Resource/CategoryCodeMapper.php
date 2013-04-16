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

	/**
	 * Retrieves category codes for a given category.
	 *
	 * @param int $category_id
	 *
	 * @return \Collection
	 */
	public function findForCategory($category_id) {
		return $this->findForCategories(array($category_id));
	}

	/**
	 * Retrieves category codes for a given set of categories.
	 *
	 * @param array $category_ids
	 *
	 * @return \Collection
	 */
	public function findForCategories($category_ids) {
		//
		// no IDs? return early so no invalid query is called.
		if (empty($category_ids)) {
			return $this->hydrateList(array());
		}
		$params    = $this->getArrayParameters($category_ids, 'id');
		$statement = $this->database->prepare("
            SELECT {$this->getColumnString()}
            FROM {$this->table}
            WHERE categoryID IN {$this->getArrayPlaceholders($category_ids, 'id')}
            ORDER BY code ASC
        ");
		$statement->execute($params);
		return $this->hydrateList($statement);
	}
}