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
			'type'       => self::TYPE_MODEL,
			'join'       => 'category',
		));
		$this->hasMap(array(
			'column'     => 'type',
			'property'   => 'workType',
			'type'       => self::TYPE_MODEL,
			'join'       => 'work_type',
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
			'type'       => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'     => 'date',
			'property'   => 'dueDate',
			'type'       => self::TYPE_DATETIME,
		));
		$this->hasMap(array(
			'column'     => 'late_date',
			'property'   => 'lateDate',
			'type'       => self::TYPE_DATETIME,
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
			'type'       => self::TYPE_DATETIME,
			'created_timestamp' => true,
		));
		//
		// TODO add TYPE_COLLECTION
		$this->hasMap(array(
			'column'     => 'mark_type',
			'property'   => 'markType',
			'type'       => self::TYPE_MODEL,
			'join'       => 'mark_type',
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

		$this->hasJoin('category', array(
			'mapper'    => new Schoolbox\Resource\CategoryMapper(),
			//
			// Define as mapper fields instead?
			// this_property:     property for model object
			// this_key_property: property for key (if there is one) if not, get
			//                    ID value from this_property
			// other_property:    property in mapped model in other object that
			//                    links to this model
			'this_key'  => 'category',
			'other_key' => 'id',

			'this_property'  => 'category',
			'this_property'  => 'category',
			'other_property' => 'id',
		));
		$this->hasJoin('files', array(
			'multiple'  => true,
			'mapper'    => new SubmissionBox\FileMapper(),
			'this_key'  => 'id',
			'other_key' => 'box_id',
		));

		$this->hasJoin('legacy_file', array(
			'multiple'  => true,
			'mapper'    => new Schoolbox\Resource\FileMapper(),
			'this_key'  => 'fid',
			'other_key' => 'id',
		));

		$this->hasJoin('work_type', array(
			'mapper'    => new SubmissionBox\WorkTypeMapper(),
			'this_key'  => 'type',
			'other_key' => 'id',
		));

		$this->hasJoin('mark_type', array(
			'mapper'    => new SubmissionBox\MarkTypeMapper(),
			'this_key'  => 'mark_type',
			'other_key' => 'mark_type_id',
		));

		//
		// TODO add Mapper->hasCollection?
		$this->hasJoin('notify', array(
			'multiple'  => true,
			'mapper'    => new SubmissionBox\NotificationMapper(),
			'this_key'  => 'id',
			'other_key' => 'box_id',
		));
	}

	/**
	 * @param int  $id
	 * @param bool $include_deleted If true, then include soft-deleted items.
	 *
	 * @return SubmissionBox
	 */
	public function findById($id, $include_deleted = false) {
		$box_file_mapper = $this->getJoinMapper('files');
		$file_mapper     = $box_file_mapper->getJoinMapper('file');
		$notify_mapper   = $this->getJoinMapper('notify');
		$worktype_mapper = $this->getJoinMapper('work_type');
		$marktype_mapper = $this->getJoinMapper('mark_type');
		$query = "
			SELECT
				{$this->getColumnString('sb')},
				{$worktype_mapper->getColumnString('wt')},
				{$marktype_mapper->getColumnString('mt')}
			FROM {$this->getTable()} sb
			LEFT JOIN {$this->getJoinSql('work_type', 'sb', 'wt')}
			LEFT JOIN {$this->getJoinSql('mark_type', 'sb', 'mt')}
			WHERE sb.id=?
		";
		if (!$include_deleted) {
			$query .= ' AND sb.deleted IS FALSE';
		}
		$statement = $this->database->prepare($query);
		$statement->execute(array($id));

		$submission_box = false;

		if (($row = $statement->fetch()) !== false) {
			$submission_box = $this->hydrate($row, 'sb');
			$submission_box->setWorkType($worktype_mapper->hydrate($row, 'wt'));
			$submission_box->setMarkType($marktype_mapper->hydrate($row, 'mt'));
		}

		//
		// TODO get submission history for a user as part of submission (or of user?)

		if ($submission_box instanceof SubmissionBox) {
			foreach ($notify_mapper->findByColumn('box_id', $submission_box->getId()) as $notify) {
				$submission_box->addNotification($notify);
			}

			//
			// Get files? (This is more evidence separate file/link mapper isn't
			// required.)
			$query = "
				SELECT
					{$box_file_mapper->getColumnString('sm')},
					{$file_mapper->getColumnString('f')}
				FROM {$box_file_mapper->getTable()} sm
				LEFT JOIN {$box_file_mapper->getJoinSql('file', 'sm', 'f')}
				WHERE sm.box_id=?
			";
			$statement = $this->database->prepare($query);
			$statement->execute(array($id));
			foreach ($statement as $row) {
				$box_file       = $box_file_mapper->hydrate($row, 'sm');
				$box_file->setFile($file_mapper->hydrate($row, 'f'));
				$submission_box->addFileLink($box_file);
			}
		}
		return $submission_box;
	}

	public function save($object) {
		$objects = $this->getModelAsCollection($object);

		//
		// Convert legacy files to storage files on saving.
		foreach ($objects as $this_object) {
			if ($legacy_file_id = $this_object->getLegacyFileId()) {
				$old_file = $this->getJoinMapper('legacy_file')->findById($legacy_file_id);
				$new_file = $this->getJoinMapper('legacy_file')->copyToStorage($old_file);

				$new_link = $this->getJoinMapper('files')->createModel();
				$new_link->setFile($new_file);
				$this_object->getFileLinks()->add($new_link);

				//
				// Remove legacy file link because it is no longer needed.
				$this_object->setLegacyFileId(null);
			}
		}
		$result = parent::save($objects);
		if ($result) {
			//
			// TODO: add pre-save, pre-delete, post-save, post-delete hooks.
			// Implement this sort of logic in those instead of in extended
			// save methods.
			// Consider a publish/subscribe event system.
			foreach ($objects as $this_object) {
				foreach ($this_object->getFileLinks() as $box_file) {
					if ($box_file->hasFile()) {
						$box_file->setSubmissionBoxId($this_object->getId());
						$this->getJoinMapper('files')->save($box_file);
					}
				}
				foreach ($this_object->getNotifications() as $notification) {
					$notification->setSubmissionBoxId($this_object->getId());
					$this->getJoinMapper('notify')->save($notification);
				}
			}
		}
		return $result;
	}

	/**
	 * @param SubmissionBox $object
	 * @param bool          $hard_delete
	 *
	 * @return bool
	 */
	public function delete($object, $hard_delete = false) {
		if ($hard_delete) {
			return parent::delete($object);
		}
		$query     = "UPDATE {$this->getTable()} SET deleted=TRUE WHERE id=?";
		$statement = $this->database->prepare($query);
		return $statement->execute(array($object->getId()));
	}
}
