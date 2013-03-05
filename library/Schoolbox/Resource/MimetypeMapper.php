<?php
/**
 * Mimetype mapper stub.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource;
use Schoolbox;

class MimetypeMapper extends Schoolbox\Mapper {
	public function getMimetypeForFilename($filename) {
		//
		// Copied from AllowedFile methods getExtension() and getMimetype()
		$extension = substr(strrchr($filename, "."), 1);
		$query = "
			SELECT mime
			FROM allowedFileExtension afe
			LEFT JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
			WHERE extension = ?
		";
		$statement = $this->database->prepare($query);
		$statement->execute(array($extension));

		if ($row = $statement->fetch()) {
			return $row['mime'];
		}
		return false;
	}
}
