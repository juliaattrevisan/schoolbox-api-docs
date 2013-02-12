<?php
/**
 * Alaress_Image_Processor_Strategy_Crop
 *
 * Crops out a rectangle from an image. The boundaries of the cropping is defined in percentages rather than
 * pixels, as this will cater for multiple images of various dimensions needing to be cropped in the same way.
 *
 * x0,y0 are the top-left coordinates of an image expressed as percentages
 * x1,y1 are the bottom-right coordinates of an image expressed as percentages
 *
 * e.g. (5,10) and (66,75) will crop from 5%,10% to 66%,75%
 *
 * The percentage conversion is done internally, so you can just put in values between 0 and 100
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Processor_Strategy_Crop extends Alaress_Image_Processor_Strategy_Abstract implements Alaress_Image_Processor_Strategy_Interface {
	private $coordinates = array();

	/**
	 * Constructor
	 *
	 * @param $args
	 */
	public function __construct($args) {
		$this->setCoordinates($args['coordinates']);
	}

	/**
	 * Manipulate image
	 *
	 * @param $image
	 * @return null
	 */
	public function manipulate($image) {

		/* Figure out resized dimensions
		 */
		$currentWidth = imagesx($image);
		$currentHeight = imagesy($image);

		/* Width of area to crop
		 */
		$src_w = $currentWidth * ($this->getCoordinates('x1') - $this->getCoordinates('x0'));
		$src_h = $currentHeight * ($this->getCoordinates('y1') - $this->getCoordinates('y0'));

		$src_x = $currentWidth * $this->getCoordinates('x0');
		$src_y = $currentHeight * $this->getCoordinates('y0');

		/* Create canvas at specified dimensions
		 */
		$canvas = imagecreatetruecolor($src_w, $src_h);

		$this->alphaFill($canvas);
		imagecopyresampled($canvas, $image, 0, 0, $src_x, $src_y, $src_w, $src_h, $src_w, $src_h);

		return $canvas;
	}

	/**
	 * Set coordinates
	 *
	 * @param $coordinates
	 */
	private function setCoordinates($coordinates) {
		$this->coordinates = $coordinates;

		/* Do conversion so we don't have to do it ourselves -_-
		 */
		$this->coordinates['x0'] *= 0.01;
		$this->coordinates['y0'] *= 0.01;
		$this->coordinates['x1'] *= 0.01;
		$this->coordinates['y1'] *= 0.01;
	}

	/**
	 * Get coordinates
	 *
	 * @param $key
	 * @return array
	 */
	private function getCoordinates($key) {
		return $this->coordinates[$key];
	}
}
