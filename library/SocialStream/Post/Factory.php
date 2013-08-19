<?php
/**
 * SocialStream_Post_Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace SocialStream\Post;

class Factory {

	/**
	 * The value of the $_POST['socialstreamAttachment'] field
	 *
	 * @var string
	 */
	private $type = '';

	/**
	 * The submitted url
	 *
 	 * @var string
	 */
	private $url = '';

	/**
	 * Regexes that permit the relevant sites
	 * These are used to spawn a SocialStream_Post_Video instance
	 *
	 * @var array
	 */
	private $videoSites = array(
		'#^(http://)?(www\.)?youtube\.com/watch\?v=([a-zA-Z0-9_\-]+)#',
		'#^(http://)?vimeo\.com/([0-9]+)#',
		'#^(http://)?www\.ted\.com/talks/([a-zA-Z0-9_]+)\.html#',
	);

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
	 * Produce an instance of SocialStream_Post_Abstract subclass
	 *
	 * @return mixed
	 */
	public function produce() {

		/* If someone chose to add a link to their post, try to determine
		 * what kind of link it is and display media accordingly
		 */
		if ($this->getType() == 'url') {

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
		}
		/* If someone chose to upload a file to their post, try to determine
		 * what kind of link it is and display media accordingly. Do this by
		 * checking the mime type of the uploaded file's name
		 */
		else if ($this->getType() == 'upload') {
		}


		/* If it was not a video, link to the internet, then assume it's
		 * just a plain old text message
		 */
		$object = new \SocialStream_Post_Url();
		$object->setType('string');

		return $object;
	}

	/* Getters/Setters
	 */

	/**
	 * Set video sites
	 *
	 * @param array $videoSites
	 */
	private function setVideoSites($videoSites) {
		$this->videoSites = $videoSites;
	}

	/**
	 * Get video sites
	 *
	 * @return array
	 */
	private function getVideoSites() {
		return $this->videoSites;
	}

	/**
	 * Set url
	 *
	 * @param string $url
	 */
	private function setUrl($url) {
		$this->url = $url;
	}

	/**
	 * Get url
	 *
	 * @return string
	 */
	private function getUrl() {
		return $this->url;
	}

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