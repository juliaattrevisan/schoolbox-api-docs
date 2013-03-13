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

	protected function getExtensionInfo($filename) {
		//
		// Copied from AllowedFile methods getExtension() and getMimetype()
		$extension = substr(strrchr($filename, "."), 1);
		$query = "
			SELECT mime, iconFile
			FROM allowedFileExtension afe
			LEFT JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
			WHERE extension = ?
		";
		$statement = $this->database->prepare($query);
		$statement->execute(array($extension));

		if ($row = $statement->fetch()) {
			return $row;
		}
		return false;
	}

	public function getMimetypeForFilename($filename) {
		$extension_info = $this->getExtensionInfo($filename);
		return $extension_info['mime'];
	}

	public function getIconForFilename($filename) {
		$extension_info = $this->getExtensionInfo($filename);
		return $extension_info['iconFile'];
	}
}
