<?php
/**
 * Collection for submission box file collections.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Collection;
use Schoolbox\FileMapper as SchoolboxFileMapper;

class FileCollection extends Collection {

	/**
	 * Checks whether this file ID is in the collection.
	 *
	 * @param $file_id
	 *
	 * @return bool
	 */
	public function isFileIdInCollection($file_id) {
		return ($this->getBy('fileId', $file_id) instanceof SubmissionBox\File);
	}

	/**
	 * Adds the file with this ID to this collection, if it's not there.
	 *
	 * @param $file_id
	 *
	 * @return bool True if it was added, false if not.
	 */
	public function addFromFileId($file_id) {
		if (!$this->isFileIdInCollection($file_id)) {
			$file_mapper = new SchoolboxFileMapper();
			$file        = $file_mapper->findById($file_id);
			$file_link   = new SubmissionBox\File();
			$file_link->setFile($file);
			$this->add($file_link);
			return true;
		}
		return false;
	}
}
