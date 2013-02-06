<?php
/**
 * Alaress_Image_Processor_Strategy_Abstract
 *
 * Canvas abstract. Used when you need to specify new dimensions for an image.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Alaress_Image_Processor_Strategy_Abstract {

	/* Canvas image resource
	 */
	protected $canvas = null;
	protected $canvasWidth = 0;
	protected $canvasHeight = 0;

	/**
	 * Specify a background colour for canvas if provided
	 * otherwise use generic old white
	 *
	 * @param $bgColorArray
	 * @return int
	 */
	protected function applyBackgroundColor($bgColorArray) {
		if (is_array($bgColorArray)) {
			$bgColor = imagecolorallocate($this->getCanvas(), $bgColorArray['red'], $bgColorArray['blue'], $bgColorArray['green']);
		}
		else {
			$bgColor = imagecolorallocate($this->getCanvas(), 255, 255, 255);
		}

		imagefilledrectangle($this->getCanvas(), 0, 0, $this->getCanvasWidth(), $this->getCanvasHeight(), $bgColor);
	}

	/**
	 * Figure out what the resultant dimensions of an image are
	 * according to a bounding box
	 *
	 * If it can fit within the canvas, just return original size
	 *
	 * @param $image
	 * @return array
	 */
	protected function boundingBox($image) {

		/* Figure out resized dimensions
		*/
		$currentWidth = imagesx($image);
		$currentHeight = imagesy($image);

		$resizedWidth = $currentWidth;
		$resizedHeight = $currentHeight;

		/* Constrain by width
		 */
		if ($currentWidth > $this->getCanvasWidth()) {
			$ratio = $currentWidth / $this->getCanvasWidth();

			$resizedWidth = $currentWidth / $ratio;
			$resizedHeight = $currentHeight / $ratio;
		}

		/* Constrain by height
		 */
		if ($resizedHeight > $this->getCanvasHeight()) {
			$ratio = $resizedHeight / $this->getCanvasHeight();

			$resizedWidth = $resizedWidth / $ratio;
			$resizedHeight = $resizedHeight / $ratio;
		}

		return array(
			'width' => $resizedWidth,
			'height' => $resizedHeight,
		);
	}

	/**
	 * Set canvas image resource
	 *
	 * @param $canvas
	 */
	protected function setCanvas($canvas) {
		$this->canvas = $canvas;
	}

	/**
	 * Get canvas image resource
	 *
	 * @return null
	 */
	protected function getCanvas() {
		return $this->canvas;
	}

	/**
	 * Set canvas height
	 *
	 * @param $canvasHeight
	 */
	protected function setCanvasHeight($canvasHeight) {
		$this->canvasHeight = $canvasHeight;
	}

	/**
	 * Get canvas height
	 *
	 * @return int
	 */
	protected function getCanvasHeight() {
		return $this->canvasHeight;
	}

	/**
	 * Set canvas width
	 *
	 * @param $canvasWidth
	 */
	protected function setCanvasWidth($canvasWidth) {
		$this->canvasWidth = $canvasWidth;
	}

	/**
	 * Get canvas width
	 *
	 * @return int
	 */
	protected function getCanvasWidth() {
		return $this->canvasWidth;
	}

    /**
     * Fills the given canvas with a blank alpha layer, so that alpha blending
     * looks passable after the image is sharpened.
     *
     * This should be called before calling imagecopyresampled() on $canvas.
     *
     * @param resource $canvas The canvas resource to fill, or
     *                         $this->getCanvas() if not set.
     *
     * @return void
     */
    protected function alphaFill($canvas = null) {
        if (!isset($canvas)) {
            $canvas = $this->getCanvas();
        }
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
    }
}
