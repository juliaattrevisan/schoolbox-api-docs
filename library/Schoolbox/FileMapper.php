<?php
/**
 * Mapper for the File model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;
use Schoolbox;

class FileMapper extends Schoolbox\Mapper {
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
			'type'       => self::TYPE_INTEGER,
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
			'type'              => self::TYPE_STRING,
			'created_timestamp' => true,
		));
		$this->hasMap(array(
			'column'            => 'last_accessed',
			'property'          => 'lastAccessed',
			'type'              => self::TYPE_STRING,
			'created_timestamp' => true,
		));
		//
		// Location is NOT a mapped property, though it has a getter/setter.
	}


	public function save($object) {
		$objects = $this->getModelAsCollection($object);
		$result  = parent::save($objects);
		if ($result) {
			foreach ($objects as $this_object) {
				//
				// If the object has a temp location: copy it from there.
				// Update the filesize with the temp location.
				$this_object->copyFromTempLocation();
			}
		}
		return $result;
	}


	public function delete($object) {
		//
		// We really need preDelete/postDelete callbacks instead of overriding
		// delete method.
		$objects = $this->getModelAsCollection($object);
		foreach ($objects as $this_object) {
			$this_object->deleteFile();
		}
		parent::delete($objects);
	}
}
