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

class MyFileMapper extends Mapper {

	protected function mapping () {
		$this->hasTable('myFile');
		$this->hasModel('\\Schoolbox\\MyFile');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));

		$this->hasMap(array(
			'column'     => 'parent',
			'property'   => 'parent',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'owner',
			'property'   => 'owner',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'realname',
			'property'   => 'filename',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'submitted',
			'property'   => 'createdAt',
			'type'       => self::TYPE_DATETIME,
		));

		$this->hasMap(array(
			'column'     => 'description',
			'property'   => 'description',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'file_size',
			'property'   => 'filesize',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'mime_type',
			'property'   => 'mime',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'deleted',
			'property'   => 'deletedAt',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasJoin('allowedFileType', array(
			'mapper'    => '\\Schoolbox\\File\\MimetypeMapper',
			'this_key'  => 'mime_type',
			'other_key' => 'mime',
		));
	}
}
