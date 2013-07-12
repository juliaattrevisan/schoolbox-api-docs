<?php
/**
 * Eportfolio_PageMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream;
use Schoolbox;

class PostMapper extends Schoolbox\Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('socialstream_post');

		$this->hasMap(array(
			'column'     => 'post_id',
			'property'   => 'postId',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'owner_id',
			'property'   => 'ownerId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'homepage_id',
			'property'   => 'homepageId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'parent_id',
			'property'   => 'parentId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'type',
			'property'   => 'type',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'mimetype',
			'property'   => 'mimetype',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'url',
			'property'   => 'url',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'body',
			'property'   => 'body',
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
	 * Find all the social stream posts for a given homepage
	 *
	 * @param $homepageId
	 * @return \Collection
	 */
	public function findAllForHomepage($homepageId) {
		$query = "
			SELECT {$this->getColumnString()}
            FROM `{$this->table}`
            WHERE homepage_id = :homepageId
            ORDER BY parent_id DESC, created_at
        ";

		$statement = $this->database->prepare($query);
		$statement->bindParam(":homepageId", $homepageId);
		$statement->execute();

		$rowset = $statement->fetchAll();

		return $this->hydrateList($rowset);
	}

	/**
	 * Spawn a subclass of SocialStream_Post_Abstract
	 *
	 * @param $row
	 * @return object
	 */
	public function createModel($row) {
		switch($row['type']) {
			case 'Video' : $model = 'SocialStream_Post_Video'; break;
			default : $model = 'SocialStream_Post_String'; break;
		}

		return new $model;
	}

	/**
	 * @param Array $row
	 * @param String $alias
	 * @param callable $callback
	 * @return Model
	 */
	public function hydrate($row, $alias = '', $callback = null) {
		$model = $this->createModel($row);

		foreach ($this->map as $map) {
			$propertyName = $map['property'];
			$columnName = $map['column'];

			if (!empty($alias)) {
				$columnName = "$alias.$columnName";
			}

			$columnValue = array_key_exists($columnName, $row) ? $row[$columnName] : null;
			$this->setModelProperty($model, $propertyName, $columnValue, $map);
		}

		if (!empty($callback) && is_callable($callback)) {
			$model = call_user_func($callback, $this, $model, $row);
		}

		return $model;
	}

}