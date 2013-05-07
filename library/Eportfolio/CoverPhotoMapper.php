<?php
/**
 * CoverPhotoMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Eportfolio;
use Eportfolio;
use Schoolbox;

class CoverPhotoMapper extends Schoolbox\Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('eportfolio_coverphoto');
		$this->hasModel('\\Eportfolio\\CoverPhoto');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'uid',
			'property'   => 'uid',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'imagehash',
			'property'   => 'imagehash',
			'type'       => self::TYPE_STRING,
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

		parent::mapping();
	}

	/**
	 * Find the latest cover photo
	 *
	 * @param $userId
	 * @return \Collection
	 */
	public function findLatest($userId) {
		$statement = $this->database->prepare("
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
            WHERE uid = :userId
            ORDER BY created_at DESC
            LIMIT 1
        ");

		$statement->bindParam(":userId", $userId);
		$statement->execute();

		$rowset = $statement->fetchAll();

		return $this->hydrateList($rowset);
	}
}