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

					$this->setProduct($object);
				}
			}

			/* Alternatively it's just a link to the internet
			 */
			$object = new \SocialStream_Post_Url();
			$object->setType('url');

			$this->setProduct($object);
		}
	}

	/**
	 * Construct a post from a link by doing mimetype analysis
	 */
	private function constructFromUpload() {
		$fileArray = $_FILES['file'];

		try {
			$resource = \Schoolbox\FileMapper::getInstance()->upload($fileArray['name'], $fileArray['tmp_name'], $_SESSION['id']);
		}
		catch (Exception $ex) {
		}

		/* Figure out if it's a video
		 */
		if ($resource->isVideo()) {
			$object = new \SocialStream_Post_Video();
			$object->setType('video');

			$this->setProduct($object);
		}
		else if ($resource->isImage()) {
			$object = new \SocialStream_Post_Url();
			$object->setType('url');

			$this->setProduct($object);
		}

		return $resource;
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
	 * @return mixed|null
	 */
	public function produce($data) {

		/* If the url option was chosen, try to curl metadata/thumbnails
		 */
		if ($this->getType() == 'url') {
			$this->constructFromLink();
			$this->populate($data);

			/* Find thumbnail from the given url in one of the many
			 * ways that they can exist, if applicable
			 */
			if (strlen($this->getProduct()->getUrl())) {
				$curl = new \Alaress_Curl_Url($this->getProduct()->getUrl());
				$curl->exec();

				/* Spawn additional stuff
				 *
				 * Optionally parse the curl result here into a dom parser
				 * That would allow for attribute gathering
				 */
				$this->spawnMetadataFromCurl($curl);
				$this->spawnThumbnailFromCurl($curl);
			}
		}
		/* If upload option was chosen, extract metadata and thumbnail information
		 * directly from already-known information (no curling needed)
		 */
		else if ($this->getType() == 'upload') {
			$uploadedFile = $this->constructFromUpload();
			$this->populate($data);

			/* Spawn additional stuff
			 */
			$this->spawnMetadataFromUpload($uploadedFile);
			$this->spawnThumbnailFromUpload($uploadedFile);
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

	/* Spawn metadata and thumbnails via different ways
	 */

	/**
	 * Get the title and description
	 *
	 * @param $curl
	 */
	private function spawnMetadataFromCurl($curl) {
		$chain = new \SocialStream\Post\Metadata\Chain($curl, $this->getProduct()->getUrl());

		$this->getProduct()->setExternalTitle($chain->getResult()->getTitle());
		$this->getProduct()->setExternalBody($chain->getResult()->getDescription());
	}

	/**
	 * Use a two-layer caching setup for spawning thumbnail images
	 * For images, thumbnails are obtained via a series of regexes
	 */
	private function spawnThumbnailFromCurl($curl) {
		$chain = new \SocialStream\Post\Thumbnail\Chain($curl, $this->getProduct()->getUrl());

		/* To prevent a double-curl, remember the place where a thumbnail was
		 * located (this is used as the cached image transform filename hash)
		 */
		if (strlen($chain->getResult())) {
			$this->getProduct()->setThumbnail($chain->getResult());

			/* Save the original in the cache location
			 */
			$originalThumbnailCache = new \SocialStream\Cache\Original();
			$originalThumbnailCache->init($this->getProduct()->getThumbnail());
		}
	}

	/**
	 * Get the title and description
	 *
	 * @param \Schoolbox\File $uploadedFile
	 */
	private function spawnMetadataFromUpload(\Schoolbox\File $uploadedFile) {
		$this->getProduct()->setExternalTitle($uploadedFile->getFilename());
		$this->getProduct()->setExternalBody('');
	}

	/**
	 * Get the thumbnail usage
	 *
	 * @param \Schoolbox\File $uploadedFile
	 */
	private function spawnThumbnailFromUpload(\Schoolbox\File $uploadedFile) {
		if ($uploadedFile->isImage()) {
			$url = "/storage/image.php?size=maxsize&hash={$uploadedFile->getHash()}";

			$this->getProduct()->setThumbnail($url);

			/* Save the original in the cache location
			 */
			$originalThumbnailCache = new \SocialStream\Cache\Original();
			$originalThumbnailCache->init($url);
		}
		else if ($uploadedFile->isVideo()) {
			$url = "/cms/sendVideo.php?type=storage&id={$uploadedFile->getHash()}";

			$this->getProduct()->setUrl($url);
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