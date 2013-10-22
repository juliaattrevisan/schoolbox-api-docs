<?php
/**
 * Updater
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Usage;

class Updater {

	/**
	 * Insert filesizes of all existing submissions
	 */
	public function updateSubmissions() {
		$dirhandle = opendir(\Cfg::get('submission_dir'));
		$mapper = new \Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper();

		while ($filepointer = readdir($dirhandle)) {
			if (($filepointer != '.') && ($filepointer != '..')) {
				$size = filesize(\Cfg::get('submission_dir') . '/' . $filepointer);

				try {
					$file = $mapper->findById(str_replace('.dat', '', $filepointer));
					$file->setFilesize($size);
					$mapper->save($file);
				}
				catch(\Exception $e) {
				}
			}
		}
	}
}