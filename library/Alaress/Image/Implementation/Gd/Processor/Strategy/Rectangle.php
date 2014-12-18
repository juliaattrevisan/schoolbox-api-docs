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
	 * Images smaller than the canvas maintain their dimensions and don't
	 * get resized if the canvas is larger.
	 *
	 * Set to true if you want it to upscale.
	 *
	 * @var bool
	 */
	private $smallImageUpscales = true;

	/**
	 * Background colours are used for deciding transparency in pngs
	 *
	 * @var array
	 */
	private $bgColors = array();

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
		$this->setBgColors($args['bgcolor']);
		$this->applyBackgroundColor($args['bgcolor']);

		$this->setSmallImageUpscales($args['smallImageUpscales']);
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

		$currentRatio = $currentWidth / $currentHeight;
		$finalRatio = $this->getCanvasWidth() / $this->getCanvasHeight();

		/* Resize if image is larger than the canvas in either dimension
		 * (or upscaling small images)
		 */
		if ($currentWidth > $this->getCanvasWidth()
			|| $currentHeight > $this->getCanvasHeight()
			|| $this->getSmallImageUpscales()
		) {
			/* Landscape
			 */
			if (($this->getCanvasWidth()/$this->getCanvasHeight()) >= 1) {

				/* Current image is more panoramic than final canvas -_-;
				 */
				if ($currentRatio > $finalRatio) {
					/* The edges that get lopped off
					 */
					$trim = abs(0.5 * ($currentHeight * ($finalRatio - $currentRatio)));

					$startX = $trim;
					$startY = 0;

					$endX = $currentWidth - 2*$trim;
					$endY = $currentHeight;
				}
				/* Canvas is more panoramic than current image -_-;
				 */
				else {
					/* The edges that get lopped off
					 */
					$trim = abs(0.5 * ($currentWidth/$finalRatio - $currentWidth/$currentRatio));

					$startX = 0;
					$startY = $trim;

					$endX = $currentWidth;
					$endY = $currentHeight - 2*$trim;
				}
			}

			/* Portrait
			 */
			else {

				/* Canvas is taller than current image -_-;
				 */
				if ($currentRatio > $finalRatio) {
					/* The edges that get lopped off
					 */
					$trim = abs(0.5 * ($currentHeight * ($finalRatio - $currentRatio)));

					$startX = $trim;
					$startY = 0;

					$endX = $currentWidth - 2*$trim;
					$endY = $currentHeight;
				}

				/* Current image is taller than final canvas -_-;
				 */
				else {
					/* The edges that get lopped off
					 */
					$trim = abs(0.5 * ($currentWidth/$finalRatio - $currentWidth/$currentRatio));

					$startX = 0;
					$startY = $trim;

					$endX = $currentWidth;
					$endY = $currentHeight - 2*$trim;
				}
			}

			/* Resize the canvas to fit the new resized dimensions, so
			 * you don't get excess canvas space
			 */
			$this->alphaFill();
			imagecopyresampled($this->getCanvas(), $image, 0, 0, $startX, $startY, $this->getCanvasWidth(), $this->getCanvasHeight(), $endX, $endY);
		}
		else {
			// Create a new truecolor canvas of the same size, and copy onto it.
			$this->setCanvas(imagecreatetruecolor($currentWidth, $currentHeight));
			$this->alphaFill();
			imagecopy($this->getCanvas(), $image, 0, 0, 0, 0, $currentWidth, $currentHeight);
		}


		return $this->getCanvas();
	}

	/* Getters/Setters
	 */

	/**
	 * Set smallImageUpscales
	 * @param boolean $smallImageUpscales
	 */
	public function setSmallImageUpscales($smallImageUpscales) {
		$this->smallImageUpscales = $smallImageUpscales;
	}

	/**
	 * Get smallImageUpscales
	 *
	 * @return boolean
	 */
	public function getSmallImageUpscales() {
		return $this->smallImageUpscales;
	}

	/**
	 * Set bgcolours
	 *
	 * @param array $bgColors
	 */
	private function setBgColors($bgColors) {
		$this->bgColors = $bgColors;
	}

	/**
	 * Get bgcolours
	 *
	 * @return array
	 */
	private function getBgColors() {
		return $this->bgColors;
	}
}
