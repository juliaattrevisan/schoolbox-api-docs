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
	 * The object that was produced by the factory
	 *
	 * @var null
	 */
	private $product = null;

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
	}

	/**
	 * Construct a post from a link by doing mimetype analysis
	 */
	private function constructFromUpload() {
		$fileArray = $_FILES['file'];

		try {
			$imageResource = \Schoolbox\FileMapper::getInstance()->upload($fileArray['name'], $fileArray['tmp_name'], $_SESSION['id']);
		}
		catch (Exception $ex) {
		}

		var_dump($imageResource->isImage());
		new \Alaress_Probe($imageResource);

		/* Figure out if it's a video
		 */
		if (preg_match('#^video/#', $fileArray['type'])) {
			die('mort');
		}
		else if (preg_match('#^image/#', $fileArray['type'])) {
			die('bart');
		}


		die('mog');
	}

	/**
	 * If it was not a video, link to the internet, etc then assume it's
	 * just a plain old text message
	 */
	private function constructDefault() {
		$object = new \SocialStream_Post_String();
		$object->setType('string');

		return $object;
	}

	/**
	 * Produce an instance of SocialStream_Post_Abstract subclass
	 * Populate it with the information from $_POST etc
	 * Spawn metadata and thumbnails where appropriate
	 *
	 * @param $data
	 * @return mixed
	 */
	public function produce($data) {
		if ($this->getType() == 'url') {
			$this->setProduct($this->constructFromLink());
			$this->populate($data);

			/* Find thumbnail from the given url in one of the many
			 * ways that they can exist, if applicable
			 */
			if (strlen($this->getProduct()->getUrl())) {
				$curl = new \Alaress_Curl_Url($this->getProduct()->getUrl());
				$curl->exec();

				/* Optionally parse the curl result here into a dom parser
				 * That would allow for attribute gathering
				 */
				$this->getProduct()->spawnMetadata($curl);
				$this->getProduct()->spawnThumbnail($curl);
			}
		}
		else if ($this->getType() == 'upload') {
			$this->setProduct($this->constructFromUpload());
			$this->populate($data);
		}
		else {
			$this->setProduct($this->constructDefault());
			$this->populate($data);
		}

		return $this->getProduct();
	}

	/**
	 * Puts data from the $data array into $object
	 * This is usually the result of a post
	 *
	 * @param $data
	 */
	private function populate($data) {
		$this->getProduct()->setRootId($data['rootId']);
		$this->getProduct()->setParentId($data['parentId']);
		$this->getProduct()->setHomepageId($data['homepageId']);
		$this->getProduct()->setOwnerId($data['ownerId']);
		$this->getProduct()->setUrl(trim($data['url']));
		$this->getProduct()->setBody(trim($data['body']));
		$this->getProduct()->setCreatedAt($data['requestTime']);
		$this->getProduct()->setUpdatedAt($data['requestTime']);
		$this->getProduct()->setDeletedAt(null);
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

	/**
	 * Set product
	 *
	 * @param null $product
	 */
	private function setProduct($product) {
		$this->product = $product;
	}

	/**
	 * Get product
	 *
	 * @return null
	 */
	private function getProduct() {
		return $this->product;
	}


}