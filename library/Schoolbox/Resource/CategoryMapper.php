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


class CategoryMapper extends Mapper {
	protected function mapping() {
		$this->hasModel('\\Schoolbox\\Resource\\Category');
		$this->hasTable('category');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'name',
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'parent',
			'property'   => 'parentId',
			'type'       => self::TYPE_INTEGER,
		));

		//
		// Joins:
		$this->hasJoin('codes', array(
			'multiple'  => true,
			'mapper'    => '\\Schoolbox\\Resource\\CategoryCodeMapper',
			'this_key'  => 'id',
			'other_key' => 'categoryID',
		));
		$this->hasJoin('files', array(
			'multiple'  => true,
			'mapper'    => '\\Schoolbox\\Resource\\FileMapper',
			'this_key'  => 'id',
			'other_key' => 'category',
		));
		//
		// Parent object: has reference to itself as a mapper.
		$this->hasJoin('parent', array(
			'multiple'  => true,
			'mapper'    => $this,
			'this_key'  => 'category',
			'other_key' => 'id',
		));
	}


	public function findById($id) {
		$code_mapper = $this->getJoinMapper('codes');
		$query = "
			SELECT
				{$this->getColumnString('c')},
				{$code_mapper->getColumnString('cc')}
			FROM {$this->getTable()} c
			LEFT JOIN {$this->getJoinSql('codes', 'c', 'cc')}
			WHERE c.id=?
		";
		$statement = $this->database->prepare($query);
		$statement->execute(array($id));

		$category = null;
		foreach ($statement as $row) {
			if (!$category instanceof $this->model) {
				$category = $this->hydrate($row, 'c');
			}
			$code = $code_mapper->hydrate($row, 'cc');
			if ($code->getCategory()->getId()) {
				$category->addCode($code);
			}
		}
		return $category;
	}
}