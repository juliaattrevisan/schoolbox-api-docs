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
		$this->hasMap(array(
			'column'     => 'owner',
			'property'   => 'owner',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'open_date',
			'property'   => 'openDate',
			//
			// Will become DATE or DATETIME type.
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'date',
			'property'   => 'dueDate',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'late_date',
			'property'   => 'lateDate',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'accept_submission',
			'property'   => 'acceptOnline',
			'type'       => self::TYPE_BOOLEAN,
		));
		$this->hasMap(array(
			'column'     => 'multi_submission',
			'property'   => 'acceptMultiple',
			'type'       => self::TYPE_BOOLEAN,
		));
		$this->hasMap(array(
			'column'     => 'created',
			'property'   => 'created',
			'type'       => self::TYPE_STRING,
			'created_timestamp' => true,
		));
		$this->hasMap(array(
			'column'     => 'mark_type',
			'property'   => 'markType',
			'type'       => self::TYPE_BOOLEAN,
		));
		$this->hasMap(array(
			'column'     => 'max_mark',
			'property'   => 'maxMark',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'rubric_result_set',
			'property'   => 'rubricResultSet',
			'type'       => self::TYPE_INTEGER,
		));

		//
		// adds listeners:
		// buildSelectQuery('\\Schoolbox\\Assessment\\Component\\SubmissionBox')
		// undefinedMethod('hardDelete', function() {hard delete})
		// $this->hasSoftDeleteField('deleted');

		//
		// $this->hasOne?
		$this->hasJoin('files', array(
			'mapper'    => new SubmissionBoxFileMapper(),
			'this_key'  => 'id',
			'other_key' => 'box_id',
		));

		//
		// $this->hasCollection?
		$this->hasJoin('notify', array(
			'multiple'  => true,
			'mapper'    => new SubmissionBoxNotificationMapper(),
			'this_key'  => 'id',
			'other_key' => 'box_id',
//			'extra'     => "`:other_alias`.`type` = 'overdue'",
		));
//		$this->hasJoin('notify_upcoming', array(
//			'mapper'    => new SubmissionBoxNotificationMapper(),
//			'this_key'  => 'id',
//			'other_key' => 'box_id',
//			'extra'     => "`:other_alias`.`type` = 'upcoming'",
//		));
//		$this->hasJoin('notify_returned', array(
//			'mapper'    => new SubmissionBoxNotificationMapper(),
//			'this_key'  => 'id',
//			'other_key' => 'box_id',
//			'extra'     => "`:other_alias`.`type` = 'returned'",
//		));
	}

	public function findById($id) {
		$box_file_mapper = $this->getJoinMapper('files');
		$file_mapper     = $box_file_mapper->getJoinMapper('file');
		$notify_mapper   = $this->getJoinMapper('notify');
		$query = "
			SELECT
				{$this->getColumnString('sb')},
				{$box_file_mapper->getColumnString('sm')},
				{$file_mapper->getColumnString('f')},
				{$notify_mapper->getColumnString('n')}
			FROM {$this->getTable()} sb
			LEFT JOIN {$this->getJoinSql('files', 'sb', 'sm')}
			LEFT JOIN {$box_file_mapper->getJoinSql('file', 'sm', 'f')}
			LEFT JOIN {$this->getJoinSql('notify', 'sb', 'n')}
			WHERE sb.id=?
		";
		$statement = $this->database->prepare($query);
		$statement->execute(array($id));

		$submission_box = false;
		foreach ($statement as $row) {
			if (!$submission_box) {
				$submission_box = $this->hydrate($row, 'sb');
				$box_file       = $box_file_mapper->hydrate($row, 'sm');
				$box_file->setFile($file_mapper->hydrate($row, 'f'));
				$submission_box->setFileLink($box_file);
				$submission_box->setFileLink($box_file);
			}
			$submission_box->addNotification($notify_mapper->hydrate($row, 'n'));
		}
		return $submission_box;
	}

	public function save($object) {
		$objects = $this->getModelAsCollection($object);
		$result  = parent::save($objects);
		if ($result) {
			foreach ($objects as $this_object) {
				if ($box_file = $this_object->getFileLink()) {
					$box_file->setSubmissionBoxId($this_object->getId());
					$this->getJoinMapper('files')->save($box_file);
				}
				foreach ($this_object->getNotifications() as $notification) {
					$notification->setSubmissionBoxId($this_object->getId());
					$this->getJoinMapper('notify')->save($notification);
				}
			}
		}
		return $result;
	}
}
