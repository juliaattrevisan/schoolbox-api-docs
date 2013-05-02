<?php
/**
 * Mimetype mapper stub.
 *
 * @todo make this a full class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\File;
use Schoolbox;

class MimetypeMapper extends Schoolbox\Mapper {

	protected function mapping() {
		$this->hasTable('allowedFileTypes');
		$this->hasModel('\\Schoolbox\\File\\Mimetype');

		$this->hasMap(array(
			'column'     => 'fileTypeId',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));

		$this->hasMap(array(
			'column'     => 'description',
			'property'   => 'description',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'mime',
			'property'   => 'mime',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'iconFile',
			'property'   => 'iconFile',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'inline',
			'property'   => 'inline',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'enable',
			'property'   => 'enable',
			'type'       => self::TYPE_STRING,
		));
	}

	/**
	 * Used to fetch a mime object for a given filename
	 *
	 * @param $filename
	 * @return Mimetype
	 */
	public function getByFilename($filename) {

		// Copied from AllowedFile methods getExtension() and getMimetype()
		$extension = substr(strrchr($filename, "."), 1);
		$query = "
			SELECT {$this->getColumnString('aft')}
			FROM allowedFileExtension afe
			LEFT JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
			WHERE extension = ?
		";
		$statement = $this->database->prepare($query);
		$statement->execute(array($extension));

		if ($row = $statement->fetch()) {
			return $this->hydrate($row, 'aft');
		}

	}

    /*
     * TODO: These are silly functions and not in the spirit of the game, please remove!
     */
	public function getMimetypeForFilename($filename) {
		$extension_info = $this->getByFilename($filename);
		return $extension_info->getMime();
	}

	public function getIconForFilename($filename) {
		$extension_info = $this->getByFilename($filename);
		return $extension_info->getIconFile();
	}
}
