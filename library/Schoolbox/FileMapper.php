<?php
/**
 * Mapper for the File model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;
use Schoolbox\Mapper;
use Schoolbox\File;

class FileMapper extends Mapper {
	protected function mapping () {
		$this->hasTable('storage');
		$this->hasModel('\\Schoolbox\\File');
		$this->hasMap(array(
			'column'     => 'storage_id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'filename',
			'property'   => 'filename',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'hash',
			'property'   => 'hash',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'filesize',
			'property'   => 'filesize',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'mime',
			'property'   => 'mime',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'uploaded_by',
			'property'   => 'uploadedBy',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'            => 'uploaded_on',
			'property'          => 'uploadedOn',
			'type'              => self::TYPE_DATETIME,
			'created_timestamp' => true,
		));
		$this->hasMap(array(
			'column'            => 'last_accessed',
			'property'          => 'lastAccessed',
			'type'              => self::TYPE_DATETIME,
			'created_timestamp' => true,
		));
		$this
			->registerCallback('postSave', array($this, 'postSave'))
			->registerCallback('postDelete', array($this, 'postDelete'))
		;
	}

	/**
	 * Copies file from temp location into a location accessible by this model.
	 *
	 * @param File $object
	 */
	public function postSave(File $object) {
		$object->copyFromTempLocation();
	}

	/**
	 * Deletes this model's file.
	 *
	 * @param File $object
	 */
	public function postDelete(File $object) {
		$object->deleteFile();
	}

	/**
	 * Convenience method for creating and saving a file into storage.
	 *
	 * @throws File\Exception if the upload was not allowed.
	 *
	 * @param string $filename      The file's name.
	 * @param string $temp_location The location of the file to save.
	 * @param int    $user_id       The user to associate with the file.
	 *
	 * @return File
	 */
	public function upload($filename, $temp_location, $user_id) {
		$file = new File();
		$file->setFilename($filename);
		if (!($file->isUploadAllowed())) {
			throw new File\Exception('Invalid mime type', File\Exception::CODE_MIMETYPE_NOT_FOUND);
		}
		$file->setTempLocation($temp_location);
		$file->setUploadedBy($user_id);
		$file->loadHashFromTempLocation();
		$file->loadMimetypeFromFilename();
		$file->loadFilesizeFromTempLocation();
		$this->save($file);
		return $file;
	}
}
