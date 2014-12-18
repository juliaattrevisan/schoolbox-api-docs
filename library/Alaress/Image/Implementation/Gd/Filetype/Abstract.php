<?php
/**
 * Defines a base for image filetypes, and defines a create method which throws
 * exception if image isn't found.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
abstract class Alaress_Image_Filetype_Abstract implements Alaress_Image_Filetype_Interface {

	/**
	 * Creates an image using the loadImageResource() method of the subclass.
	 *
	 * @param  $filename
	 * @throws Exception_Schoolbox_NotFound if file can't be read
	 * @return resource
	 */
	public function create($filename) {
		$im = false;
		if (is_file($filename) && is_readable($filename)) {
			$im = $this->loadImageResource($filename);
		}
		if (!$im) {
			throw new Exception_Schoolbox_NotFound(sprintf('Cannot read file "%s"', $filename));
		}
		return $im;
	}
}
