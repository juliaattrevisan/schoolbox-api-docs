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

		//
		// I think category.teacher is obsolete - it's replaced by owning user

		//
		// I think category.template is obsolete - it's replaced by
		// homepage_config entry.

		//
		// I think category.code is obsolete, replaced by multiple entries in
		// categoryCode table.
	}


	public function getById($id) {
		//
		// Fetch no more than one row for category, owner, group type, template.
		$user_mapper = new Schoolbox\UserMapper();
		$query = "
			SELECT
				{$this->getColumnString('c')},
				{$user_mapper->getColumnString('owner')},
				g.type AS `group_type`,
				cf.template AS `template`
			FROM {$this->table} AS c
				LEFT JOIN userAccess AS a ON a.sid=c.id AND a.type=:access_type AND a.service=:service_type
				LEFT JOIN user AS owner ON owner.id=a.uid
				LEFT JOIN userGroups AS g ON g.cid=c.id
				LEFT JOIN homepage_config AS cf ON cf.cid=c.id
			WHERE c.id=:id
		";
		$statement = $this->database->prepare($query);
		$statement->execute(array(
			'access_type'  => Schoolbox\UserAccess::ACCESS_FULL,
			'service_type' => Schoolbox\UserAccess::SERVICE_HOMEPAGE,
			'id'           => $id,
		));
		if ($row = $statement->fetch()) {
			$category = $this->hydrate($row, 'c');
			$category->setGroupType($row['group_type']);
			$category->setTemplate($row['template']);
			if (isset($row['owner.id'])) {
				$category->setOwner($user_mapper->hydrate($row, 'owner'));
			}
			return $category;
		}
		return false;

		//
		// Attach homepagey stuff.
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