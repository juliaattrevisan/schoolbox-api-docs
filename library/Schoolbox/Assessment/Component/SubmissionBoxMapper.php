<?php
/**
 * Mapper for the SubmissionBox model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component;
use Schoolbox;

class SubmissionBoxMapper extends Schoolbox\Mapper {

	protected function mapping () {
		$this->hasTable('submission_box');
		$this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBox');
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

		$this->hasJoin('files', array(
			'mapper'    => new SubmissionBoxFileMapper(),
			'this_key'  => 'id',
			'other_key' => 'box_id',
		));
	}

	public function findById($id) {
		$box_file_mapper = new SubmissionBoxFileMapper();
		$file_mapper     = new Schoolbox\Resource\FileMapper();
		$query = "
			SELECT
				{$this->getColumnString('sb')},
				{$box_file_mapper->getColumnString('sm')},
				{$file_mapper->getColumnString('f')}
			FROM {$this->getTable()} sb
			LEFT JOIN {$this->getJoinSql('files', 'sb', 'sm')}
			LEFT JOIN {$box_file_mapper->getJoinSql('file', 'sm', 'f')}
			WHERE sb.id=?
		";
		$statement = $this->database->prepare($query);
		$statement->execute(array($id));
		if ($row = $statement->fetch()) {
			$submission_box = $this->hydrate($row, 'sb');
			$box_file       = $box_file_mapper->hydrate($row, 'sm');
			$box_file->setFile($file_mapper->hydrate($row, 'f'));
			$submission_box->setStorage($box_file);
			return $submission_box;
		}
		return false;
	}
}
