<?php
/**
 * Mapper for the SubmissionBoxFile model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component;
use Schoolbox;

class SubmissionBoxFileMapper extends Schoolbox\Mapper {

	protected function mapping () {
		$this->hasTable('submission_box_file');
		$this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBoxFile');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'storage_id',
			'property'   => 'fileId',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'box_id',
			'property'   => 'submissionBoxId',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasJoin('file', array(
			'mapper'    => new \Schoolbox\Resource\FileMapper(),
			'this_key'  => 'storage_id',
			'other_key' => 'storage_id',
		));
	}

	public function save($object) {
		//
		// Create/save associated file item first if it isn't already saved.
		$objects = $this->getModelAsCollection($object);
		foreach ($objects as &$this_object) {
			//
			// pre-save callback on this object?
			// subscribe('preSave:SubmissionBoxFileMapper', $this_object)
			// in mapper->save(): publish('preSave:'.$this->name, true(default))
			if ($file = $this_object->getFile()) {
				if (!($file->getId())) {
					$this->getJoinMapper('file')->save($file);
					$this_object->setFileId($file->getId());
				}
			}
		}
		unset($this_object);
		return parent::save($objects);
	}
}
