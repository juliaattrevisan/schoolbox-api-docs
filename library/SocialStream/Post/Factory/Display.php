<?php
/**
 * SocialStream_Post_Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace SocialStream\Post\Factory;

class Display extends AbstractFactory {

	/**
	 * Constructor
	 *
	 * @param $url
	 */
	public function __construct($url) {
		$this->setUrl(trim($url));
	}

	/**
	 * Produce an instance of SocialStream_Post_Abstract subclass
	 *
	 * @param null $data
	 * @return mixed|\SocialStream_Post_Url|\SocialStream_Post_Video
	 */
	public function produce($data = null) {

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
}