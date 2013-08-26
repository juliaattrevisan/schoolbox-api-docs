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
	 * Produce an instance of SocialStream_Post_Abstract subclass
	 * Populate it with the information from $_POST etc
	 * Spawn metadata and thumbnails where appropriate
	 *
	 * @param $data
	 * @return mixed|null
	 */
	public function produce($data) {

		/* If the url option was chosen, try to curl metadata/thumbnails
		 * but only if a url was provided
		 */
		if (($this->getType() == 'url') && strlen($this->getUrl())) {
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
		else if (($this->getType() == 'upload') && ($_FILES['file']['size'] > 0)) {
			$uploadedFile = $this->constructFromUpload();
			$this->populate($data);

			/* Spawn additional stuff
			 */
			$this->spawnMetadataFromUpload($uploadedFile);
			$this->spawnThumbnailFromUpload($uploadedFile);
		}
		/* If resource option was chosen,extract metadata and thumbnail information
		 * directly from already-known information (no curling needed) but only if a
		 * url was provided
		 */
		else if (($this->getType() == 'resource') && strlen($this->getUrl())) {
			$resource = $this->constructFromResource($this->getUrl());
			$this->populate($data);

			/* Spawn additional stuff
			 */
			$this->spawnMetadataFromResource($resource);
			$this->spawnThumbnailFromResource($resource);
		}
		else {
			$this->setProduct($this->constructDefault());
			$this->populate($data);
		}

		return $this->getProduct();
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
		/* Other files
		 */
		else {
			$object = new \SocialStream_Post_Url();
			$object->setType('url');

			$this->setProduct($object);
		}

		return $resource;
	}

	/**
	 * Construct a post from an already-uploaded resource
	 *
	 * @param $url
	 * @return object
	 */
	private function constructFromResource($url) {
		$bits = parse_url($url);
		$query = $bits['query'];

		parse_str($query, $values);
		$fileId = $values['id'];

		$fileMapper = new \Schoolbox\Resource\FileMapper();
		$file = $fileMapper->findById($fileId);

		/* TODO: Assume just ordinary files for now
		 */
		$object = new \SocialStream_Post_Url();
		$object->setType('url');

		$this->setProduct($object);

		return $file;
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

		/* Set up video thumbnails
		 */
		if ($uploadedFile->isVideo()) {
			$url = "/cms/sendVideo.php?type=storage&hash={$uploadedFile->getHash()}";

			$this->getProduct()->setUrl($url);
		}
		/* Set up image thumbnails
		 */
		else if ($uploadedFile->isImage()) {
			$url = "/storage/image.php?size=maxsize&hash={$uploadedFile->getHash()}";

			$this->getProduct()->setThumbnail($url);
			$this->getProduct()->setUrl($url);

			/* Save the original in the cache location
			 */
			$originalThumbnailCache = new \SocialStream\Cache\Original();
			$originalThumbnailCache->init($url);
		}
		/* Set up storage stream location and its icon thumbnails
		 */
		else {
			$url = "/storage/fetch.php?hash={$uploadedFile->getHash()}";
			$this->getProduct()->setUrl($url);

			$chain = new \SocialStream\Post\Thumbnail\Chain(null, $uploadedFile->getFilename());
			$this->getProduct()->setThumbnail($chain->getResult());
		}
	}

	/**
	 * Get the title and description
	 *
	 * @param $file
	 */
	private function spawnMetadataFromResource($file) {
		$this->getProduct()->setExternalTitle($file->getFilename());
		$this->getProduct()->setExternalBody('');
	}

	/**
	 * Get the thumbnail usage
	 *
	 * Why does this tell me I'm not logged in, and then save the html
	 * in the file which would normally contain image data? wtf
	 *
	 * Is because fopen() is trying to get to a password-protected place
	 * send.php kicks you out at ->getPermission() (about line 47)
	 *
	 * Force an icon representation instead
	 *
	 * @param $file
	 */
	private function spawnThumbnailFromResource($file) {

//		/* Set up video thumbnails
//		 */
//		if ($file->isVideo()) {
//		}
//		/* Set up image thumbnails
//		 */
//		else if ($file->isImage()) {
//			$url = "/send.php?id={$file->getId()}";
//
//			$this->getProduct()->setThumbnail($url);
//			$this->getProduct()->setUrl($url);
//
//			/* Save the original in the cache location
//			 */
//			$originalThumbnailCache = new \SocialStream\Cache\Original();
//			$originalThumbnailCache->init($url);
//		}
//		/* Set up icon thumbnails
//		 */
//		else {
//			$url = "/send.php?id={$file->getId()}";
//			$this->getProduct()->setUrl($url);
//
//			$chain = new \SocialStream\Post\Thumbnail\Chain(null, $file->getFilename());
//			$this->getProduct()->setThumbnail($chain->getResult());
//		}

		/* Force icon representation for now
		 */
		$url = "/send.php?id={$file->getId()}";
		$this->getProduct()->setUrl($url);

		$chain = new \SocialStream\Post\Thumbnail\Chain(null, $file->getFilename());
		$this->getProduct()->setThumbnail($chain->getResult());
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