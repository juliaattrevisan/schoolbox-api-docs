<?php
/**
 * Alaress_Image_Processor_Strategy_StretchyButton
 *
 * Integration of standalone StretchyButton implementation at tools.alaress-dev.com.au
 * Originally made for Tom to make progressclaim buttons faster.
 *
 * This takes 3 component images, the left/middle/right components. It resizes
 * the middle one to a specified dimension, then pastes the left and right on
 * the edges (left- and right-aligned respectively).
 *
 * This allows you to dynamically produce buttons with edges. The ascii art
 * below shows the superposition of the image bits (left, middle, right)
 *

 L   +  MMMMM  +  R  =  LMMMR

 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Processor_Strategy_StretchyButton extends Alaress_Image_Processor_Strategy_Abstract implements Alaress_Image_Processor_Strategy_Interface {
	private $leftImage = null;
	private $middleImage = null;
	private $rightImage = null;

	/**
	 * Constructor
	 *
	 * @param $args
	 */
	public function __construct($args) {
		$this->setLeftImage($args['left']);
		$this->setMiddleImage($args['middle']);
		$this->setRightImage($args['right']);
	}

	/**
	 * Manipulate image
	 *
	 * @param $image
	 * @return mixed
	 */
	public function manipulate($image) {
		$this->addMiddleImage($image);
		$this->addLeftImage($image);
		$this->addRightImage($image);

		return $image;
	}

	/**
	 * Adds the stretchy middle part of the button
	 *
	 * @param $image
	 * @return void
	 */
	private function addMiddleImage($image) {
		if (file_exists($this->getMiddleImage())) {
			$middleImage = imagecreatefrompng($this->getMiddleImage());

			list($width, $height) = getimagesize($this->getMiddleImage());

			$this->alphaFill($image);
			imagecopyresampled($image, $middleImage, 0, 0, 0, 0, imagesx($image), $height, $width, $height);
		}
	}

	/**
	 * Adds the left-aligned image to the button
	 *
	 * @param $image
	 * @return void
	 */
	private function addLeftImage($image) {
		if (file_exists($this->getLeftImage())) {
			$leftImage = imagecreatefrompng($this->getLeftImage());

			list($width, $height) = getimagesize($this->getLeftImage());

			$this->alphaFill($image);
			imagecopyresampled($image, $leftImage, 0, 0, 0, 0, $width, $height, $width, $height);
		}
	}

	/**
	 * Adds the right-aligned image to the button
	 *
	 * @param $image
	 * @return void
	 */
	private function addRightImage($image) {
		if (file_exists($this->getRightImage())) {
			$rightImage = imagecreatefrompng($this->getRightImage());

			list($width, $height) = getimagesize($this->getRightImage());

			$this->alphaFill($image);
			imagecopyresampled($image, $rightImage, imagesx($image)-$width, 0, 0, 0, $width, $height, $width, $height);
		}
	}

	/**
	 * Set left image
	 *
	 * @param $leftImage
	 */
	private function setLeftImage($leftImage) {
		$this->leftImage = $leftImage;
	}

	/**
	 * Get left image
	 *
	 * @return null
	 */
	private function getLeftImage() {
		return $this->leftImage;
	}

	/**
	 * Set middle image
	 *
	 * @param $middleImage
	 */
	private function setMiddleImage($middleImage) {
		$this->middleImage = $middleImage;
	}

	/**
	 * Get middle image
	 *
	 * @return null
	 */
	private function getMiddleImage() {
		return $this->middleImage;
	}

	/**
	 * Set right image
	 *
	 * @param $rightImage
	 */
	private function setRightImage($rightImage) {
		$this->rightImage = $rightImage;
	}

	/**
	 * Get right image
	 *
	 * @return null
	 */
	private function getRightImage() {
		return $this->rightImage;
	}
}
