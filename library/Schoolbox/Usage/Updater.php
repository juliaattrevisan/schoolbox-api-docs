<?php
/**
 * Updater
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Usage;

class Updater {

	/**
	 * Insert filesizes of all existing submissions
	 */
	public function updateSubmissions() {
		$dirhandle = opendir(\Config::get('submission_dir'));

		$mapper = new \Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper();
		$mimeMapper = new \Schoolbox\File\MimetypeMapper();

		while ($filepointer = readdir($dirhandle)) {
			if (($filepointer != '.') && ($filepointer != '..')) {
				$size = filesize(\Config::get('submission_dir') . '/' . $filepointer);

				try {
					$file = $mapper->findById(str_replace('.dat', '', $filepointer));
					$file->setFilesize($size);
					$file->setMimeType($mimeMapper->getByFilename($file->getFilename()));
					$mapper->save($file);
				}
				catch(\Exception $e) {
				}
			}
		}
	}

	/**
	 * Insert filesizes of all existing returns
	 */
	public function updateReturns() {
		$dirhandle = opendir(\Config::get('returns_dir'));

		$mapper = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper();
		$mimeMapper = new \Schoolbox\File\MimetypeMapper();

		while ($filepointer = readdir($dirhandle)) {
			if (($filepointer != '.') && ($filepointer != '..')) {
				$size = filesize(\Config::get('returns_dir') . '/' . $filepointer);

				try {
					$file = $mapper->findById(str_replace('.dat', '', $filepointer));
					$file->setFilesize($size);
					$file->setMimeType($mimeMapper->getByFilename($file->getFilename()));

					$mapper->save($file);
				}
				catch(\Exception $e) {
				}
			}
		}
	}
}
