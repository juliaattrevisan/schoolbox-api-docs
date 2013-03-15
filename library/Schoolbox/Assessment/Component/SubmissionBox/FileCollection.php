<?php
/**
 * Collection for submission box file collections.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Collection;

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
}
