<?php
/**
 * Mapper for files in the resource browser.
 *
 * Differs from Schoolbox\File in that files of this type display in the
 * resource browser, files in Schoolbox\File don't.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Resource;
use Schoolbox\Mapper;
use Schoolbox\File       as Storage;
use Schoolbox\FileMapper as StorageMapper;

class FileMapper extends Mapper {

	/**
	 * @var \Schoolbox\FileMapper
	 */
	protected $storage_mapper;

	public function setup() {
		parent::setup();
		$this->storage_mapper = new StorageMapper();
	}

	/**
	 * @param  \Schoolbox\FileMapper $storage_mapper
	 * @return $this
	 */
	public function setStorageMapper($storage_mapper) {
		$this->storage_mapper = $storage_mapper;
		return $this;
	}

	/**
	 * @return \Schoolbox\FileMapper
	 */
	public function getStorageMapper() {
		return $this->storage_mapper;
	}

	protected function mapping() {
		$this->hasTable('file');
		$this->hasModel('\\Schoolbox\\Resource\\File');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'realname',
			'property'   => 'filename',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'mime_type',
			'property'   => 'mimeType',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'comment',
			'property'   => 'comment',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'description',
			'property'   => 'description',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'hidden',
			'property'   => 'hidden',
			'type'       => self::TYPE_STRING,
		));
		$this->hasMap(array(
			'column'     => 'category',
			'property'   => 'folder',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'file_size',
			'property'   => 'filesize',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'owner',
			'property'   => 'owner',
			'type'       => self::TYPE_MODEL,
            'join'       => 'user',
		));
		$this->hasMap(array(
			'column'            => 'submitted',
			'property'          => 'submitted',
			'type'              => self::TYPE_DATETIME,
			'created_timestamp' => true,
		));
		$this->hasMap(array(
			'column'            => 'modified',
			'property'          => 'modified',
			'type'              => self::TYPE_DATETIME,
			'updated_timestamp' => true,
		));

		$this->hasSoftDeleteBooleanField('deleted');

		$this->hasJoin('user', array(
             'mapper'    => '\\Schoolbox\\UserMapper',
             'this_key'  => 'owner',
             'other_key' => 'id',
		));
	}

	/**
	 * Copies this file to the storage engine, or returns the existing copy if
	 * it's already there.
	 *
	 * @param File $file The file object to copy to storage.
	 *
	 * @return Storage The new storage item or existing item.
	 */
	public function copyToStorage(File $file) {
		$storage = $this->storage_mapper->createModel();
		$storage
			->setFilename($file->getFilename())
			->setTempLocation($file->getFileLocation())
			->setUploadedBy($file->getOwner());
		$storage->loadHashFromTempLocation();
		$storage->loadMimetypeFromFilename();
		$storage->loadFilesizeFromTempLocation();

		$existing = $this->storage_mapper->findOneByColumn('hash', $storage->getHash());
		if ($existing) {
			return $existing;
		}
		else {
			return $storage;
		}
	}
}