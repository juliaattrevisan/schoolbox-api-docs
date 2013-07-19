<?php
/**
 * Mapper for the SubmissionBoxFile model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox;

class FileMapper extends Schoolbox\Mapper {

	protected function mapping () {
		$this->hasTable('submission_box_file');
		$this->hasModel('\\Schoolbox\\Assessment\\Component\\SubmissionBox\\File');
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
			'mapper'    => '\\Schoolbox\\FileMapper',
			'this_key'  => 'storage_id',
			'other_key' => 'storage_id',
		));
		$this->registerCallback('preSave', array($this, 'preSave'));
	}

	/**
	 * Creates storage file mapper object.
	 *
	 * @param File $object
	 *
	 * @return bool
	 */
	public function preSave(File $object) {
		//
		// Create/save associated file item first if it isn't already saved.
		$file_mapper = $this->getJoinMapper('file');
		//
		// pre-save callback on this object?
		// subscribe('preSave:SubmissionBoxFileMapper', $this_object)
		// in mapper->save(): publish('preSave:'.$this->name, true(default))
		if ($file = $object->getFile()) {
			//
			// Does this file's hash exist already?
			// If so, use the existing file instead of the uploaded one.
			// Otherwise, a file was uploaded - create a new file entry
			// instead of overwriting the existing one.
			$existing_files = $file_mapper->findByColumn('hash', $file->getHash());
			$file_id        = null;
			if ($existing_files->count() > 0) {
				$file_id = $existing_files->first()->getId();
			}
			else {
				//
				// TODO dependency inject session access
				$file->setUploadedBy($_SESSION['id'] ?: 0);
				$file_mapper->create($file);
				$file_id = $file->getId();
			}
			$object->setFileId($file_id);
		}
		return true;
	}
}
