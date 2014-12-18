<?php
/**
 * Alaress_Image_Processor_Strategy_Placement
 *
 * Place an image on a canvas at one of the nine cardinal locations. Defaults to 'topleft'
 *
 * topleft, top, topright
 * left, middle, right
 * bottomleft, bottom, bottomright
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Processor_Strategy_Placement extends Alaress_Image_Processor_Strategy_Abstract implements Alaress_Image_Processor_Strategy_Interface {

	/* Positioning of the image within the canvas
	 */
	private $position = 'topleft';

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
		$this->setPosition($args['position']);

		/* Create canvas at specified dimensions
		 */
		$canvas = imagecreatetruecolor($this->getCanvasWidth(), $this->getCanvasHeight());

		$this->setCanvas($canvas);

		/* Apply background color
		 */
		$this->setBgColors($args['bgcolor']);
		$this->applyBackgroundColor($args['bgcolor']);
	}

	/**
	 * Manipulate image
	 *
	 * @param $image
	 * @return mixed
	 */
	public function manipulate($image) {

		/* Figure out resized dimensions
		*/
		$resizedWidth = imagesx($image);
		$resizedHeight = imagesy($image);

		/* Constrain to half canvas width and position where told
		 */
		$positioning = $this->determinePositioning($resizedWidth, $resizedHeight);

		$bgColor = $this->getBgColors();

		$this->alphaFill();
		imagecopyresampled($this->getCanvas(), $image, $positioning['dst_x'], $positioning['dst_y'], 0, 0, $resizedWidth, $resizedHeight, imagesx($image), imagesy($image));

		return $this->getCanvas();
	}

	/**
	 * Figure out where the image resampling starts and ends, on the canvas
	 *
	 * @param $resizedWidth
	 * @param $resizedHeight
	 * @return array
	 */
	private function determinePositioning($resizedWidth, $resizedHeight) {

		/* Top row
		 */
		if ($this->getPosition() == 'topleft') {
			$dst_x = 0;
			$dst_y = 0;
		}
		else if ($this->getPosition() == 'top') {
			$dst_x = floor(($this->getCanvasWidth() - $resizedWidth)/2);
			$dst_y = 0;
		}
		else if ($this->getPosition() == 'topright') {
			$dst_x = floor($this->getCanvasWidth() - $resizedWidth);
			$dst_y = 0;
		}

		/* Middle row
		 */
		else if ($this->getPosition() == 'left') {
			$dst_x = 0;
			$dst_y = floor(($this->getCanvasHeight() - $resizedHeight)/2);
		}
		else if ($this->getPosition() == 'middle') {
			$dst_x = floor(($this->getCanvasWidth() - $resizedWidth)/2);
			$dst_y = floor(($this->getCanvasHeight() - $resizedHeight)/2);
		}
		else if ($this->getPosition() == 'right') {
			$dst_x = floor($this->getCanvasWidth() - $resizedWidth);
			$dst_y = floor(($this->getCanvasHeight() - $resizedHeight)/2);
		}

		/* Bottom row
		 */
		else if ($this->getPosition() == 'bottomleft') {
			$dst_x = 0;
			$dst_y = floor(($this->getCanvasHeight() - $resizedHeight));
		}
		else if ($this->getPosition() == 'bottom') {
			$dst_x = floor(($this->getCanvasWidth() - $resizedWidth)/2);
			$dst_y = floor(($this->getCanvasHeight() - $resizedHeight));
		}
		else if ($this->getPosition() == 'bottomright') {
			$dst_x = floor($this->getCanvasWidth() - $resizedWidth);
			$dst_y = floor(($this->getCanvasHeight() - $resizedHeight));
		}

		/* Default
		 */
		else {
			$dst_x = 0;
			$dst_y = 0;
		}

		return array(
			'dst_x' => $dst_x,
			'dst_y' => $dst_y
		);
	}

	/* Getters/Setters
	 */

	/**
	 * Set position
	 *
	 * @param $position
	 */
	private function setPosition($position) {
		$this->position = $position;
	}

	/**
	 * Get position
	 *
	 * @return string
	 */
	private function getPosition() {
		return $this->position;
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
