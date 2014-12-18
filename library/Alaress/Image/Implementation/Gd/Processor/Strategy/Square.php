<?php
/**
 * Alaress_Image_Processor_Strategy_Square
 *
 * Constrain an image to fit within a specified width and height which is measured
 * from the center of the source image
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Processor_Strategy_Square extends Alaress_Image_Processor_Strategy_Abstract implements Alaress_Image_Processor_Strategy_Interface {

	/**
	 * Constructor
	 *
	 * @param $args
	 */
	public function __construct($args) {
		$this->setCanvasWidth($args['width']);
		$this->setCanvasHeight($args['width']);

		/* Create canvas at specified dimensions
		 */
		$canvas = imagecreatetruecolor($this->getCanvasWidth(), $this->getCanvasHeight());

		$this->setCanvas($canvas);
	}

	/**
	 * Manipulate image
	 *
	 * @param $image
	 * @return null
	 */
	public function manipulate($image) {

		/* Center of source image
		 */
		$currentWidth = imagesx($image);
		$currentHeight = imagesy($image);

		$centerX = floor($currentWidth/2);
		$centerY = floor($currentHeight/2);

		/* Landscape
		 * Constrain by width
		 */
		if ($centerX > $centerY) {
			$ratio = $currentHeight / $this->getCanvasHeight();

			$resizedWidth = $currentWidth / $ratio;
			$resizedHeight = $currentHeight / $ratio;

			$startX = ($currentWidth - $currentHeight)/2;
			$startY = 0;
		}

		/* Portrait
		 */
		else if ($centerX < $centerY) {
			$ratio = $currentWidth / $this->getCanvasWidth();

			$resizedWidth = $currentWidth / $ratio;
			$resizedHeight = $currentHeight / $ratio;

			$startX = 0;
			$startY = ($currentHeight - $currentWidth)/2;
		}

		/* Square already
		 */
		else {
			$resizedWidth = $this->getCanvasWidth();
			$resizedHeight = $this->getCanvasHeight();

			$startX = 0;
			$startY = 0;
		}

        $this->alphaFill();

		/* Resize the canvas to fit the new resized dimensions, so
		 * you don't get excess canvas space
		 */
		imagecopyresampled($this->getCanvas(), $image, 0, 0, $startX, $startY, $resizedWidth, $resizedHeight, imagesx($image), imagesy($image));

		return $this->getCanvas();
	}
}
