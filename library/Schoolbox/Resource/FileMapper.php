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
	protected function mapper() {
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
			'column'     => 'owner',
			'property'   => 'owner',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'            => 'submitted',
			'property'          => 'submitted',
			'type'              => self::TYPE_STRING,
			'created_timestamp' => true,
		));
		$this->hasMap(array(
			'column'            => 'modified',
			'property'          => 'modified',
			'type'              => self::TYPE_STRING,
			'updated_timestamp' => true,
		));
	}
}