<?php
/**
 * Report_Staff_Image
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Report_Staff_Image {
	private $externalUserHandler = null;
	private $imageHandler = null;
	private $groupHandler = null;
	private $staffID = null;
	private $size = 'small';

	/* Small image dimensions
	 */
	private $imageWidth = 100;
	private $imageHeight = 100;

	/* Large image dimensions
	 */
	private $largeImageWidth = 200;
	private $largeImageHeight = 200;

	/**
	 * Constructor
	 *
	 * @param $staffID
	 */
	public function __construct($staffID) {
		$this->setExternalUserHandler(new External_User());
		$this->setImageHandler(new Alaress_Image());
		$this->setGroupHandler(new Group());

		$this->setStaffId($staffID);
		$this->setSize($_GET['size']);

		$this->spawnImage();
	}

	/**
	 * Check permissions. By defaul,t only parents and superusers can view this page
	 *
	 * @return bool
	 */
	private function checkPermissions() {
		if ($this->getGroupHandler()->isParent() || $this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff()) {
			return true;
		}

		return false;
	}

	/**
	 * Spawn image
	 */
	private function spawnImage() {
		$imageRecord = $this->getExternalUserHandler()->getLatestStaffImageFor($this->getStaffId());

		if ($imageRecord && $this->checkPermissions()) {
			$documentType = $imageRecord['DocumentType'];

			$photoPath = tempnam(Cfg::get('zip_dir'), 'synport');
			$photoHandle = fopen($photoPath, "w");

			fwrite($photoHandle, $imageRecord['Document']);
			fclose($photoHandle);
		}
		/* Default thumbnail
		 */
		else {
			$documentType = 'JPG';
			$photoPath = WEB_root . '/images/no_portrait.jpg';
		}

		/* Get desired image sizes
		 */
		if ($this->getSize() == 'small') {
			$width = $this->getImageWidth();
			$height = $this->getImageHeight();
		}
		else {
			$width = $this->getLargeImageWidth();
			$height = $this->getLargeImageHeight();
		}

		/* Make image
		 */
		$image = $this->getImageHandler()->imageResize($photoPath, $width, $height, false);

		switch($documentType) {
			case 'JPG':
				header('Content-type: image/jpeg');
				header("Content-Disposition: filename={$this->getStaffId()}.jpg");
				imagejpeg($image);
				break;

			case 'GIF':
				header('Content-type: image/gif');
				header("Content-Disposition: filename={$this->getStaffId()}.gif");
				imagegif($image);
				break;

			case 'PNG':
				header('Content-type: image/png');
				header("Content-Disposition: filename={$this->getStaffId()}.png");
				imagepng($image);
				break;

			default: break;
		}
	}

	/**
	 * Set external user handler
	 *
	 * @param $externalUserHandler
	 */
	private function setExternalUserHandler($externalUserHandler) {
		$this->externalUserHandler = $externalUserHandler;
	}

	/**
	 * Get external user handler
	 *
	 * @return null
	 */
	private function getExternalUserHandler() {
		return $this->externalUserHandler;
	}

	/**
	 * Set image handler
	 *
	 * @param $imageHandler
	 */
	private function setImageHandler($imageHandler) {
		$this->imageHandler = $imageHandler;
	}

	/**
	 * Get image handler
	 *
	 * @return null
	 */
	private function getImageHandler() {
		return $this->imageHandler;
	}

	/**
	 * Set image width;
	 *
	 * @param $imageWidth
	 */
	private function setImageWidth($imageWidth) {
		$this->imageWidth = $imageWidth;
	}

	/**
	 * Get image width
	 *
	 * @return int
	 */
	private function getImageWidth() {
		return $this->imageWidth;
	}

	/**
	 * Set image height
	 *
	 * @param $imageHeight
	 */
	private function setImageHeight($imageHeight) {
		$this->imageHeight = $imageHeight;
	}

	/**
	 * Get image height
	 *
	 * @return int
	 */
	private function getImageHeight() {
		return $this->imageHeight;
	}

	/**
	 * Set staff id
	 *
	 * @param $staffID
	 */
	private function setStaffID($staffID) {
		$this->staffID = $staffID;
	}

	/**
	 * Get staff id
	 *
	 * @return null
	 */
	private function getStaffID() {
		return $this->staffID;
	}

	/**
	 * Set group handler
	 *
	 * @param $groupHandler
	 */
	public function setGroupHandler($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * Get group handler
	 *
	 * @return null
	 */
	public function getGroupHandler() {
		return $this->groupHandler;
	}

	/**
	 * Set large image height
	 *
	 * @param $largeImageHeight
	 */
	public function setLargeImageHeight($largeImageHeight) {
		$this->largeImageHeight = $largeImageHeight;
	}

	/**
	 * Get large image height
	 *
	 * @return int
	 */
	public function getLargeImageHeight() {
		return $this->largeImageHeight;
	}

	/**
	 * Set large image width
	 *
	 * @param $largeImageWidth
	 */
	public function setLargeImageWidth($largeImageWidth) {
		$this->largeImageWidth = $largeImageWidth;
	}

	/**
	 * Get large image width
	 *
	 * @return int
	 */
	public function getLargeImageWidth() {
		return $this->largeImageWidth;
	}

	/**
	 * Set size
	 *
	 * @param $size
	 */
	public function setSize($size) {
		if (($size == 'small') || ($size == 'large')) {
			$this->size = $size;
		}
	}

	/**
	 * Get size
	 *
	 * @return null
	 */
	public function getSize() {
		return $this->size;
	}
}
