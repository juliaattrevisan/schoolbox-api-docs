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
	public function getFile() {
		$stack = $this->getStack();
		$item  = $stack[0];
		if ($item instanceof File) {
			return $item->getFile();
		}
	}
	public function getId() {
		$stack = $this->getStack();
		$item  = $stack[0];
		if ($item instanceof File) {
			return $item->getId();
		}
	}

	public function isFileIdInCollection($file_id) {
		foreach ($this as $file_link) {
			if ($file_link->getFile()->getId() == $file_id) {
				return true;
			}
		}
		return false;
	}

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
