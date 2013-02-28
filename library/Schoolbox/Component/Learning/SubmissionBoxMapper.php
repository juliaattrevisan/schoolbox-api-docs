<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Component\Learning;
use Schoolbox;

class SubmissionBoxMapper extends Schoolbox\Mapper {

	protected function mapping () {
		$this->hasTable('submission_box');
		$this->hasModel('\\Schoolbox\\Component\\Learning\\SubmissionBox');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'category',
			'property'   => 'category',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'type',
			'property'   => 'type',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'name',
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'description',
			'property'   => 'description',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'fid',
			'property'   => 'legacyFileId',
			'type'       => self::TYPE_INTEGER,
		));
	}

	public function findById($id) {
		$submission_box_files_mapper = new SubmissionBoxFileMapper();
		$query = "
			SELECT
				{$this->getColumnString('sb')},
				{$submission_box_files_mapper->getColumnString('sm')}
			FROM {$this->getTable()} sb
			LEFT JOIN {$submission_box_files_mapper->getTable()} sm ON sb.id=sm.box_id
			WHERE sb.id=?
		";
		$statement = $this->database->prepare($query);
		$statement->execute(array($id));
		if ($row = $statement->fetch()) {
			$submission_box = $this->hydrate($row, 'sb');
			$submission_box->setStorage($submission_box_files_mapper->hydrate($row, 'sm'));
			return $submission_box;
		}
	}

	public function save($object) {

	}
}
