<?php
/**
 * Alaress_Image_Processor_Strategy_Constrain
 *
 * Constrain an image to fit within a specified width and height
 *
 * The entire original image will be resized with aspect preserved, to fill the maximum amount
 * of space possible without having any of the original image cropped out.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Processor_Strategy_Constrain extends Alaress_Image_Processor_Strategy_Abstract implements Alaress_Image_Processor_Strategy_Interface {

	/**
	 * Constructor
	 *
	 * @param $args
	 */
	public function __construct($args) {
		$this->setCanvasWidth($args['width']);
		$this->setCanvasHeight($args['height']);

		/* Create canvas at specified dimensions
		 */
		$canvas = imagecreatetruecolor($this->getCanvasWidth(), $this->getCanvasHeight());

		$this->setCanvas($canvas);

		/* Apply background color
		 */
		$this->applyBackgroundColor($args['bgcolor']);
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
		$dimensions = $this->boundingBox($image);

		/* Resize the canvas to fit the new resized dimensions, so
		 * you don't get excess canvas space
		 */
		$resizedCanvas = imagecreatetruecolor($dimensions['width'], $dimensions['height']);
        $this->alphaFill($resizedCanvas);
		imagecopyresampled($resizedCanvas, $image, 0, 0, 0, 0, $dimensions['width'], $dimensions['height'], imagesx($image), imagesy($image));

		return $resizedCanvas;
	}
}
