<?php
/**
 * Component_Eportfolio_BlogMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Eportfolio_BlogMapper extends Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('eportfolio_component_blog');
		$this->hasModel('Component_Eportfolio_Blog');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => Mapper::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'page_id',
			'property'   => 'pageId',
			'type'       => Mapper::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'uid',
			'property'   => 'uid',
			'type'       => Mapper::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'title',
			'property'   => 'title',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'content',
			'property'   => 'content',
			'type'       => Mapper::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'created_at',
			'property'   => 'created_at',
			'type'       => Mapper::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'updated_at',
			'property'   => 'updated_at',
			'type'       => Mapper::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'deleted_at',
			'property'   => 'deleted_at',
			'type'       => Mapper::TYPE_DATETIME,
		));

		parent::mapping();
	}

	/**
	 * Retrieves a collection of records ordered by a particular
	 * column in a particular order
	 *
	 * http://stackoverflow.com/questions/2542410/how-do-i-set-order-by-params-using-prepared-pdo-statement
	 *
	 * @param $args
	 * @return bool|Model
	 */
	public function findByOrder($args) {
		$statement = $this->database->prepare("
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
            WHERE `{$args['identifierName']}` = :identifierValue
            ORDER BY {$args['column']} {$args['order']}
        ");

		$statement->bindParam(':identifierValue', $args['identifierValue']);

		$statement->execute();

		$rowset = $statement->fetchAll();

		return $this->hydrateList($rowset);
	}

	/**
	 * Retrieves a list of recently-created blogs
	 *
	 * @param $limit
	 * @return Collection
	 */
	public function findRecent($limit) {
		$query = "
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
            ORDER BY created_at DESC
            LIMIT :limit
		";

		$statement = $this->database->prepare($query);

		$statement->bindParam(':limit', $limit, PDO::PARAM_INT);

		$statement->execute();

		$rowset = $statement->fetchAll();

		return $this->hydrateList($rowset);
	}
}