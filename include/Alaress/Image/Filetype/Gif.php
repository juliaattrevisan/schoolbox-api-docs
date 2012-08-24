<?php
/**
 * Alaress_Image_Filetype_Gif
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Filetype_Gif implements Alaress_Image_Filetype_Interface {

	/**
	 * Create an image
	 *
	 * @param $filename
	 * @return resource
	 */
	public function create($filename) {
		return imagecreatefromgif($filename);
	}

	/**
	 * Save image to file system
	 *
	 * @param $image
	 * @param $filename
	 */
	public function save($image, $filename) {
		imagegif($image, $filename);
	}

	/**
	 * Stream image to browser directly
	 *
	 * @param $image
	 */
	public function stream($image) {
		header('Content-type: image/gif');
		imagegif($image);
	}
}
