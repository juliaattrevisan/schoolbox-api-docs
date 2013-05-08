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
use Schoolbox\Collection;


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
            ORDER BY code ASC, categoryID ASC
        ");
		$statement->execute($params);
		return $this->hydrateList($statement);
	}

	/**
	 * Helper method for adding codes to all categories within this collection.
	 *
	 * @todo Consider making this method generic to populate any collection
	 *
	 * @param Collection $collection        A list of categories, or objects
	 *                                      containing categories.
	 * @param callable   $retrieve_function A callback for retrieving category
	 *                                      from each $category_collection.
	 *                                      If absent, assumes each member is a
	 *                                      Category object.
	 *
	 * @return void
	 */
	public function applyCodesToCategories($collection, $retrieve_function = null) {
		$cat_id_list = array();

		if (!is_callable($retrieve_function)) {
			$retrieve_function = function($item) { return $item; };
		}

		//
		// Get each category ID, and then get each code from the category.
		foreach ($collection as $item) {
			$cat_id_list[] = $retrieve_function($item)->getId();
		}
		$grouped_codes = array();
		foreach ($this->findForCategories(array_unique($cat_id_list)) as $code) {
			$grouped_codes[$code->getCategory()->getId()][] = $code;
		}

		//
		// Add codes to each category
		foreach ($collection as $item) {
			$category = $retrieve_function($item);
			$cat_id   = $category->getId();
			if (!empty($grouped_codes[$cat_id])) {
				foreach ($grouped_codes[$cat_id] as $code) {
					$category->addCode($code);
				}
			}
		}
	}
}