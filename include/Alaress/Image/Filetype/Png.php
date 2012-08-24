<?php
/**
 * Alaress_Image_Filetype_Png
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Filetype_Png implements Alaress_Image_Filetype_Interface {
	protected $quality = 0;

	/**
	 * Create an image
	 *
	 * @param $filename
	 * @return resource
	 */
	public function create($filename) {
		return imagecreatefrompng($filename);
	}

	/**
	 * Save image to file system
	 *
	 * @param $image
	 * @param $filename
	 */
	public function save($image, $filename) {
		imagepng($image, $filename, $this->getQuality());
	}

	/**
	 * Stream image to browser directly
	 *
	 * @param $image
	 */
	public function stream($image) {
		header('Content-type: image/png');
		imagepng($image);
	}

	/**
	 * Set quality
	 *
	 * @param $quality
	 */
	private function setQuality($quality) {
		$this->quality = $quality;
	}

	/**
	 * Get quality
	 *
	 * @return int
	 */
	private function getQuality() {
		return $this->quality;
	}
}
