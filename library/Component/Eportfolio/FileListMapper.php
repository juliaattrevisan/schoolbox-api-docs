<?php
/**
 * Component_Eportfolio_FileListMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Component\Eportfolio;
use Schoolbox;

class FileListMapper extends Schoolbox\Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('eportfolio_component_filelist_item');
		$this->hasModel('\\Component\\Eportfolio\\FileList');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'page_id',
			'property'   => 'pageId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'file_id',
			'property'   => 'fileId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'created_at',
			'property'   => 'createdAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'updated_at',
			'property'   => 'updatedAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'deleted_at',
			'property'   => 'deletedAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasJoin('files', array(
			'multiple'  => true,
			'mapper'    => '\\Schoolbox\\MyFileMapper',
			'this_key'  => 'file_id',
			'other_key' => 'id',
		));

		parent::mapping();
	}

	/**
	 * Find all published image files
	 *
	 * TODO: This can probably be combined with findRecent() as they are similar
	 * enough in functionality
	 *
	 * @return Collection
	 */
	public function findAll() {
		$query = "
            SELECT *
            FROM `{$this->table}`
            LEFT JOIN myFile ON myFile.id = {$this->table}.file_id
            WHERE myFile.mime_type LIKE '%image/%'
		";

		$statement = $this->database->prepare($query);

		$statement->bindParam(':limit', $limit, \PDO::PARAM_INT);

		$statement->execute();

		$rowset = $statement->fetchAll();

		return $this->hydrateList($rowset);
	}

	/**
	 * Retrieves a list of recently-published images
	 *
	 * @param $uid
	 * @param $limit
	 * @return Collection
	 */
	public function findRecent($uid, $limit) {
		$query = "
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
            LEFT JOIN myFile ON myFile.id = {$this->table}.file_id
            WHERE myFile.mime_type LIKE '%image/%'
            AND myFile.owner = :uid
            GROUP BY {$this->table}.file_id
            ORDER BY created_at DESC
            LIMIT :limit
		";

		$statement = $this->database->prepare($query);

		$statement->bindParam(':uid', $uid, \PDO::PARAM_INT);
		$statement->bindParam(':limit', $limit, \PDO::PARAM_INT);

		$statement->execute();

		$rowset = $statement->fetchAll();

		return $this->hydrateList($rowset);
	}

	/**
	 * Find all the published files that belong to a particular page
	 *
	 * @param $pageId
	 * @return Schoolbox\Collection
	 */
	public function findForPage($pageId) {
		$file_mapper = $this->getJoinMapper('files');
		$mime_mapper = $file_mapper->getJoinMapper('allowedFileType');

		$query = "
            SELECT
            	{$this->getColumnString('fl')},
            	{$file_mapper->getColumnString('f')},
            	{$mime_mapper->getColumnString('af')}
            FROM `{$this->table}` fl
            LEFT JOIN {$this->getJoinSql('files', 'fl', 'f')}
            LEFT JOIN {$file_mapper->getJoinSql('allowedFileType', 'f', 'af')}
            WHERE fl.page_id = :pageId
            ORDER BY created_at DESC
		";

		$statement = $this->database->prepare($query);

		$statement->bindParam(':pageId', $pageId, \PDO::PARAM_INT);

		$statement->execute();

		$collection = new \Schoolbox\Collection();

		foreach ($statement as $row) {
			$fileListItem = $this->hydrate($row, 'fl');

			$file = $file_mapper->hydrate($row, 'f');
			$mime = $mime_mapper->hydrate($row, 'af');

			/* Put mime type instance into file instance
			 */
			$file->setMime($mime);

			/* Put file instance into file list item instance
			 */
			$fileListItem->setFile($file);

			/* Put file list item instance into collection
			 */
			$collection->add($fileListItem);
		}

		return $collection;
	}
}