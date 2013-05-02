<?php
/**
 * Component_Eportfolio_FileListMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Eportfolio_FileListMapper extends Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('eportfolio_component_filelist_item');
		$this->hasModel('Component_Eportfolio_FileList');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => Mapper::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'component_instance_id',
			'property'   => 'component_instance_id',
			'type'       => Mapper::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'file_id',
			'property'   => 'fileId',
			'type'       => Mapper::TYPE_INTEGER,
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
	 * Retrieves a list of recently-published images
	 *
	 * @param $limit
	 * @return Collection
	 */
	public function findRecent($limit) {
		$query = "
            SELECT *
            FROM `{$this->table}`
            LEFT JOIN myFile ON myFile.id = {$this->table}.file_id
            WHERE myFile.mime_type LIKE '%image/%'
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