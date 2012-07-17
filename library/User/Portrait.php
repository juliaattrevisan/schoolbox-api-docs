<?php
/**
 * User_Portrait
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class User_Portrait {
	private $userHandler = null;
	private $groupHandler = null;
	private $externalHandler = null;
	private $imageHandler = null;
	private $user = array();

	/* Image dimensions
	 */
	private $width = -1;
	private $height = -1;

	/* Placeholder image file
	 */
	private $placeholder = '';
	private $isPlaceholder = true;

	/**
	 * Constructor
	 *
	 * @param $externalUserId
	 * @param $width
	 * @param $height
	 * @param $userHandler
	 * @param $groupHandler
	 * @param $externalUserHandler
	 * @param $imageHandler
	 */
	public function __construct($externalUserId, $width, $height, $userHandler, $groupHandler, $externalUserHandler, $imageHandler) {
		$this->setPlaceholder(WEB_root . '/images/core/placeholder.png');

		$this->setUserHandler($userHandler);
		$this->setGroupHandler($groupHandler);
		$this->setExternalHandler($externalUserHandler);
		$this->setImageHandler($imageHandler);

		/* The user whose portrait is currently being viewed
		 */
		$this->setUser($this->getUserHandler()->getBySynergy($externalUserId));

		/* Desired image dimensions
		 */
		$this->setWidth($width);
		$this->setHeight($height);
	}

	/**
	 * Stream it
	 *
	 * @return bool
	 */
	public function stream() {
		$image = $this->generatePortrait();

		if ($this->isPlaceholder()) {
			header("Content-Type: image/png");
			header("Content-Disposition: filename={$this->getExternalUserId()}.png");
			imagejpeg($image);

		}
		else {
			header("Content-Type: image/jpg");
			header("Content-Disposition: filename={$this->getExternalUserId()}.jpg");
			imagejpeg($image);
		}
	}

	/**
	 * Create an image resource
	 *
	 * @return bool
	 */
	private function generatePortrait() {

		/* Use placeholder by default
		 */
		$portraitPath = $this->getPlaceholder();

		/* If you are able to view this portrait, try finding image file
		 * Otherwise use a placeholder image instead
		 */
		if ($this->canViewImage()) {
			$portraitPathExists = $this->getExternalHandler()->getPortrait($this->getExternalUserId());

			/* If the portrait path exists, use it
			 */
			if ($portraitPathExists) {
				$this->setIsPlaceholder(false);

				$portraitPath = $portraitPathExists;
			}
		}

		/* Generate image resource
		 */
		$image = $this->getImageHandler()->imageResize($portraitPath, $this->getHeight(), $this->getWidth(), false);

		return $image;
	}

	/**
	 * Determine whether a portrait can be seen or not
	 *
	 * @return bool
	 */
	private function canViewImage() {

		/* Everyone can always see their own pics
		 * Otherwise implement specific policies
		 */
		if ($_SESSION['id'] == $this->getUserId()) {
			return true;
		}

		/* Staff members can always see all portraits
		 */
		if ($this->getGroupHandler()->isStaff()) {
			return true;
		}

		/* Parents can always see their children's portraits
		 */
		if ($this->getGroupHandler()->isParentOfChild($this->getUserId())) {
			return true;
		}

		/* Students can see other students' portraits if Hide Student Photo policy is disabled
		 */
		if ((Cfg::get('protect_student_photo') == 0) && ($this->getGroupHandler()->isStudent()) && ($this->getGroupHandler()->isStudent($this->getUserId()))) {
			return true;
		}

		/* Staff members can see other staff members' portraits if Hide Teacher Photo policy is disabled
		 */
		if ((Cfg::get('protect_teacher_photo') == 0) && $this->getGroupHandler()->isStaff($this->getUserId())) {
			return true;
		}

		return false;
	}

	/**
	 * Get user id
	 *
	 * @return mixed
	 */
	private function getUserId() {
		return $this->user['uid'];
	}

	/**
	 * Get external id
	 *
	 * @return mixed
	 */
	private function getExternalUserId() {
		return $this->user['synergy_id'];
	}

	/* Getters/Setters/
	 */

	/**
	 * Set external handler
	 *
	 * @param $externalHandler
	 */
	private function setExternalHandler($externalHandler) {
		$this->externalHandler = $externalHandler;
	}

	/**
	 * Get external handler
	 *
	 * @return null
	 */
	private function getExternalHandler() {
		return $this->externalHandler;
	}

	/**
	 * Set group handler
	 *
	 * @param $groupHandler
	 */
	private function setGroupHandler($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * Get group handler
	 *
	 * @return null
	 */
	private function getGroupHandler() {
		return $this->groupHandler;
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
	 * Set user handler
	 *
	 * @param $userHandler
	 */
	private function setUserHandler($userHandler) {
		$this->userHandler = $userHandler;
	}

	/**
	 * Get user handler
	 *
	 * @return null
	 */
	private function getUserHandler() {
		return $this->userHandler;
	}

	/**
	 * Set width
	 *
	 * @param $width
	 */
	private function setWidth($width) {
		$this->width = $width;
	}

	/**
	 * Get width
	 *
	 * @return int
	 */
	private function getWidth() {
		return $this->width;
	}

	/**
	 * Set height
	 *
	 * @param $height
	 */
	private function setHeight($height) {
		$this->height = $height;
	}

	/**
	 * Get height
	 *
	 * @return int
	 */
	private function getHeight() {
		return $this->height;
	}

	/**
	 * Set user
	 *
	 * @param $user
	 */
	private function setUser($user) {
		$this->user = $user;
	}

	/**
	 * Get user
	 *
	 * @return array
	 */
	private function getUser() {
		return $this->user;
	}

	/**
	 * Set placeholder
	 *
	 * @param $placeholder
	 */
	private function setPlaceholder($placeholder) {
		$this->placeholder = $placeholder;
	}

	/**
	 * Get placeholder
	 *
	 * @return string
	 */
	private function getPlaceholder() {
		return $this->placeholder;
	}

	/**
	 * Set placeholder boolean
	 *
	 * @param $isPlaceholder
	 */
	private function setIsPlaceholder($isPlaceholder) {
		$this->isPlaceholder = $isPlaceholder;
	}

	/**
	 * Find out if this is a placeholder image or not
	 *
	 * @return bool
	 */
	private function isPlaceholder() {
		return $this->isPlaceholder;
	}
}
