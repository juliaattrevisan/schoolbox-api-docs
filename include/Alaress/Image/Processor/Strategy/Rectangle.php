<?php
/**
 * Alaress_Image_Processor_Strategy_Rectangle
 *
 * Constrain an image to fit within a specified width and height which is measured
 * from the center of the source image
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Processor_Strategy_Rectangle extends Alaress_Image_Processor_Strategy_Abstract implements Alaress_Image_Processor_Strategy_Interface {

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
		 */
		if (($this->getCanvasWidth()/$this->getCanvasHeight()) >= 1) {
			$finalRatio = $this->getCanvasWidth() / $this->getCanvasHeight();

			$height = $currentWidth / $finalRatio;

			$startX = 0;
			$startY = $centerY - ($height/2);

			$endX = imagesx($image);
			$endY = $currentHeight - 2*$startY;
		}

		/* Portrait
		 */
		else {
			$finalRatio = $this->getCanvasHeight() / $this->getCanvasWidth();

			$width = $currentHeight / $finalRatio;

			$startY = 0;
			$startX = $centerX - ($width/2);

			$endX = $currentWidth - 2*$startX;
			$endY = imagesy($image);
		}

		/* Resize the canvas to fit the new resized dimensions, so
		 * you don't get excess canvas space
		 */
		imagecopyresampled($this->getCanvas(), $image, 0, 0, $startX, $startY, $this->getCanvasWidth(), $this->getCanvasHeight(), $endX, $endY);

		return $this->getCanvas();
	}
}
