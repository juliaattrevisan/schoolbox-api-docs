<?php
/**
 * Creational
 *
 * Created populated SocialStream Post objects for further
 * manipulation in controllers
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Factory;


class Creational extends AbstractFactory {

	/**
	 * The way in which things were uploaded
	 * (one of url, upload, resource)
	 *
	 * @var string
	 */
	private $type = '';

	/**
	 * Constructor
	 *
	 * @param $type
	 * @param $url
	 */
	public function __construct($type, $url) {
		$this->setType($type);
		$this->setUrl(trim($url));
	}

	/**
	 * Construct a post from a link by doing url analysis
	 *
	 * @return \SocialStream_Post_Url|\SocialStream_Post_Video
	 */
	private function constructFromLink() {

		/* If a url was provided, determine what kind it was
		 */
		if (strlen($this->getUrl())) {

			/* Figure out if it's a video
			 */
			foreach ($this->getVideoSites() as $currentPattern) {
				if (preg_match($currentPattern, $this->getUrl())) {
					$object = new \SocialStream_Post_Video();
					$object->setType('video');

					return $object;
				}
			}

			/* Alternatively it's just a link to the internet
			 */
			$object = new \SocialStream_Post_Url();
			$object->setType('url');

			return $object;
		}

		/* If it was not a video, link to the internet, then assume it's
		 * just a plain old text message
		 */
		$object = new \SocialStream_Post_Url();
		$object->setType('string');

		return $object;
	}

	/**
	 * Construct a post from a link by doing mimetype analysis
	 */
	private function constructFromUpload() {

		new \Alaress_Probe($_FILES);
		die('mog');
	}

	/**
	 * Produce an instance of SocialStream_Post_Abstract subclass
	 *
	 * @return mixed
	 */
	public function produce() {

		if ($this->getType() == 'url') {
			return $this->constructFromLink();
		}
		else if ($this->getType() == 'upload') {
			return $this->constructFromUpload();
		}
		else {
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set type
	 *
	 * @param string $type
	 */
	private function setType($type) {
		$this->type = $type;
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	private function getType() {
		return $this->type;
	}
}