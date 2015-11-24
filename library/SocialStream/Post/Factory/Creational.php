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

use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\UserMapper;
use SocialStream\UrlDetection;

class Creational extends AbstractFactory
{

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
     * @var \SocialStream_Post_Abstract
     */
    private $product = null;

    /**
     * This class knows how to figure out which urls
     * are Storage and Resource ones
     *
     * @var UrlDetection
     */
    private $urlDetection = null;

    /**
     * Constructor
     *
     * @param $url
     */
    public function __construct($url)
    {
        $this->setUrl(trim($url));
        $this->setUrlDetection(new UrlDetection($url));
    }

    /**
     * Produce an instance of SocialStream_Post_Abstract subclass
     * Populate it with the information from $_POST etc
     * Spawn metadata and thumbnails where appropriate
     *
     * @param $data
     * @return mixed|null
     */
    public function produce($data)
    {

        // if data contains an attachment
        if (is_array($data['attachment'])) {
            $factory = new CollectorFactory();
            try {
                // posts (should) only have one attachment
                /** @var Storage\Model $storage */
                $storage = $factory->build($data['attachment'])->getFirst();
                $this->parseStorageMetadata($storage, $data);
            } catch (\Schoolbox\Resource\Exception $e) {
                // this means the user 'attached' a file that failed upload, but submitted the form anyway
                // default back to plaintext
                $this->setProduct($this->constructDefault());
                $this->populate($data);
            }
        } /* Treat local urls differently as they should bypass the caching
		 * stuff entirely - this applies to the Resource browser mainly,
		 * but also when people input a local url manually
		 */
        elseif ($this->getUrlDetection()->isLocalUrl()) {
            /* When a Resource url is provided
			 */
            if ($this->getUrlDetection()->isResourceUrl()) {
                $resource = $this->constructFromResource($this->getUrl());
                $this->populate($data);

                /* Spawn additional stuff
				 */
                $this->spawnMetadataFromResource($resource);
                $this->spawnThumbnailFromResource($resource);
            } // Homepage URLs: load the link from the website
            elseif ($this->getUrlDetection()->isHomepageUrl()) {
                $homepage = $this->constructFromHomepage($this->getUrl());
                $this->populate($data);
                $this->spawnMetadataFromHomepage($homepage);
                $this->spawnThumbnailFromHomepage($homepage);
            } /* When a Storage url is provided, manufacture metadata and thumbnail
			 * as if they had been uploaded
			 */
            elseif ($this->getUrlDetection()->isStorageUrl()) {
                $fileMapper = \Schoolbox\Resource\Storage\Mapper::getInstance();
                $file = $fileMapper->findByHash($this->getUrlDetection()->getHash());

                $uploadedFile = $this->constructFromUpload($file);
                $this->populate($data);

                /* Spawn additional stuff
				 */
                $this->spawnMetadataFromUpload($uploadedFile);
                $this->spawnThumbnailFromUpload($uploadedFile);
            }
        } /* If upload option was chosen, extract metadata and thumbnail information
		 * directly from already-known information (no curling needed)
		 */
        elseif (($this->getType() == 'upload') && ($_FILES['file']['size'] > 0)) {
            $fileArray = $_FILES['file'];

            try {
                $resource = \Schoolbox\Resource\Storage\Mapper::getInstance()->accept($fileArray['name'], $fileArray['tmp_name'], $_SESSION['id']);
                $this->parseStorageMetadata($resource, $data);
            } catch (\Exception $ex) {
            }
        } /* Try to process as a url instead
		 */
        else {
            if (strlen($this->getUrl())) {
                /* Prepend Scheme before using
				 */
                $url = new \Url($this->getUrl());
                $url->prependScheme();
                $this->setUrl($url->getUrl());

                $this->constructFromUrl();
                $this->populate($data);

                /* Find thumbnail from the given url in one of the many
				 * ways that they can exist, if applicable
				 */
                $curl = new \Alaress_Curl_Url($this->getProduct()->getUrl());

                $curl->addOptions(array(
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1500.95 Safari/537.36',
                ));

                $curl->exec();

                /* Spawn additional stuff
				 *
				 * Optionally parse the curl result here into a dom parser
				 * That would allow for attribute gathering
				 */
                $this->spawnMetadataFromCurl($curl);
                $this->spawnThumbnailFromCurl($curl);
            } /* Plain text messsage
			 */
            else {
                $this->setProduct($this->constructDefault());
                $this->populate($data);
            }
        }
        return $this->getProduct();
    }


    /**
     * @param Storage\Model $storage
     * @param array    $data
     */
    protected function parseStorageMetadata(Storage\Model $storage, array $data)
    {
        $uploadedFile = $this->constructFromUpload($storage);
        $this->populate($data);

        /* Spawn additional stuff
         */
        $this->spawnMetadataFromUpload($uploadedFile);
        $this->spawnThumbnailFromUpload($uploadedFile);
    }

    /**
     * Construct a post from a link by doing url analysis
     */
    private function constructFromUrl()
    {

        /* Figure out if it's a video
		 */
        foreach ($this->getVideoSites() as $currentPattern) {
            if (preg_match($currentPattern, $this->getUrl())) {
                $object = new \SocialStream_Post_Video();
                $object->setType('video');

                $this->setProduct($object);

                return;
            }
        }

        /* Check if we have been given a URL to an image */
        if ($filename = pathinfo(parse_url($this->getUrl(), PHP_URL_PATH), PATHINFO_BASENAME)) {
            try {
                $file = \Schoolbox\File\MimetypeMapper::getInstance()->getByFilename($filename);
                if ($file->isImage()) {
                    $object = new \SocialStream_Post_Image();
                    $object->setType('image');
                    $this->setProduct($object);
                    return;
                }
            } catch (\Exception $e) {
            }
        }

        /* Alternatively it's just a link to the internet
		 */
        $object = new \SocialStream_Post_Url();
        $object->setType('url');

        $this->setProduct($object);

        return;
    }

    /**
     * Construct a post from a link by doing mimetype analysis
     *
     * @param  Storage\Model $resource
     * @return Storage\Model
     */
    private function constructFromUpload(Storage\Model $resource)
    {

        /* Figure out if it's a video
		 */
        if ($resource->getMimeType()->isVideo()) {
            $object = new \SocialStream_Post_Video();
            $object->setType('video');
        } /* Other files
		 */
        elseif ($resource->getMimetype()->isImage()) {
            $object = new \SocialStream_Post_Image();
            $object->setType('image');
        } else {
            $object = new \SocialStream_Post_Url();
            $object->setType('url');
        }

        $this->setProduct($object);

        return $resource;
    }

    /**
     * Construct a post from an already-uploaded resource
     *
     * @param $url
     * @return \Schoolbox\Resource\File
     */
    private function constructFromResource($url)
    {
        $bits = parse_url($url);
        $query = $bits['query'];

        parse_str($query, $values);
        $fileId = $values['id'];

        $fileMapper = new \Schoolbox\Resource\FileMapper();
        $file = $fileMapper->findById($fileId);

        if ($file->getMimeType()->isVideo()) {
            $object = new \SocialStream_Post_Video();
            $object->setType('video');
        } elseif ($file->getMimetype()->isImage()) {
            $object = new \SocialStream_Post_Image();
            $object->setType('image');
        } else {
            $object = new \SocialStream_Post_Url();
            $object->setType('url');
        }

        $this->setProduct($object);

        return $file;
    }

    /**
     * Construct a post from a homepage URL
     *
     * @param  string $url
     * @return Category
     */
    private function constructFromHomepage($url)
    {
        $object = new \SocialStream_Post_Url();
        $object->setType('url');
        $this->setProduct($object);
        $homepageId = $this->getUrlDetection()->getHomepageIdFromUrl();
        return CategoryMapper::getInstance()->findById($homepageId);
    }

    /**
     * If it was not a video, link to the internet, etc then assume it's
     * just a plain old text message
     */
    private function constructDefault()
    {
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
    private function populate($data)
    {
        $this->getProduct()->setRootId($data['rootId']);
        $this->getProduct()->setParentId($data['parentId']);
        $this->getProduct()->setHomepageId($data['homepageId']);
        // Owner ID is probably passed as an int value, convert to actual user
        $owner = $data['ownerId'];
        if (!$owner instanceof User) {
            $userMapper = new UserMapper();
            try {
                $owner = $userMapper->findById($owner);
            } catch (\Schoolbox\Mapper\Exception $e) {
                // noop: retain blank user
            }
        }
        $this->getProduct()->setOwner($owner);
        $this->getProduct()->setBody(trim($data['body']));
        $this->getProduct()->setCreatedAt($data['requestTime']);
        $this->getProduct()->setUpdatedAt($data['requestTime']);
        $this->getProduct()->setDeletedAt(null);

        /* This is set in a special way in socialstream/modifyForm.php
         */
        $this->getProduct()->setUrl($this->getUrl());
    }

    /* Spawn metadata and thumbnails via different ways
	 */

    /**
     * Get the title and description
     *
     * @param $curl
     */
    private function spawnMetadataFromCurl($curl)
    {
        $chain = new \SocialStream\Post\Metadata\Chain($curl, $this->getProduct()->getUrl());

        $this->getProduct()->setExternalTitle($chain->getResult()->getTitle());
        $this->getProduct()->setExternalBody($chain->getResult()->getDescription());
    }

    /**
     * Use a two-layer caching setup for spawning thumbnail images
     * For images, thumbnails are obtained via a series of regexes
     */
    private function spawnThumbnailFromCurl($curl)
    {
        $chain = new \SocialStream\Post\Thumbnail\Chain($curl, $this->getProduct()->getUrl());

        /* To prevent a double-curl, remember the place where a thumbnail was
		 * located (this is used as the cached image transform filename hash)
		 *
		 * Avoid caching icons
		 */
        if (strlen($chain->getResult())) {
            $this->getProduct()->setThumbnail($chain->getResult());

            /* Save the original in the cache location
			 * but don't cache icons
			 */
            if (!preg_match('/^icon:/', $chain->getResult())) {
                $cache = new \SocialStream\Cache\Original();
                $cache->initPost($this->getProduct());

                /* Thumbnail metadata was found, but thumbnail itself
				 * isn't there (broken link)
				 */

                if (!$cache->hasThumbnail()) {
                    $this->getProduct()->setThumbnail('');
                }
            }
        }
    }

    /**
     * Get the title and description
     *
     * @param Storage\Model $uploadedFile
     */
    private function spawnMetadataFromUpload(Storage\Model $uploadedFile)
    {
        $this->getProduct()->setExternalTitle($uploadedFile->getBasename());
        $this->getProduct()->setExternalBody('');
    }

    /**
     * Get the thumbnail usage
     *
     * @param Storage\Model $uploadedFile
     */
    private function spawnThumbnailFromUpload(Storage\Model $uploadedFile)
    {

        /* Set up video thumbnails
		 */
        if ($uploadedFile->getMimeType()->isVideo()) {
            $url = "/cms/sendVideo.php?type=storage&id={$uploadedFile->getHash()}";

            $this->getProduct()->setUrl($url);
        } /* Set up image thumbnails
		 */
        elseif ($uploadedFile->getMimeType()->isImage()) {
            $url = "/storage/image.php?size=maxsize&hash={$uploadedFile->getHash()}";

            $this->getProduct()->setThumbnail($url);
            $this->getProduct()->setUrl($url);

            /* Save the original in the cache location
			 */
            $cache = new \SocialStream\Cache\Original();
            $cache->initPost($this->getProduct());
        } /* Set up storage stream location and its icon thumbnails
		 */
        else {
            $url = "/storage/fetch.php?hash={$uploadedFile->getHash()}";
            $this->getProduct()->setUrl($url);
            $this->getProduct()->setThumbnail('icon:'.$uploadedFile->getMimetype()->getIconFile());
        }
    }

    /**
     * Get the title and description
     *
     * @param $file
     */
    private function spawnMetadataFromResource($file)
    {
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
     * @param \Schoolbox\Resource\File $file
     */
    private function spawnThumbnailFromResource($file)
    {

        /* Set up video thumbnails
		 */
        if ($file->getMimeType()->isVideo()) {
        } /* Set up image thumbnails
		 */
        elseif ($file->getMimeType()->isImage()) {
            $url = "/send.php?id={$file->getId()}";

            $this->getProduct()->setThumbnail($url);
            $this->getProduct()->setUrl($url);

            /* Save the original in the cache location
			 */
            $cache = new \SocialStream\Cache\Original();
            $cache->initPost($this->getProduct());
        } /* Set up icon thumbnails
		 */
        else {
            $url = "/send.php?id={$file->getId()}";
            $this->getProduct()->setUrl($url);
            $this->getProduct()->setThumbnail('icon:'.$file->getMimetype()->getIconFile());
        }

//		/* Force icon representation for now
//		 */
//		$url = "/send.php?id={$file->getId()}&save=1";
//		$this->getProduct()->setUrl($url);
//
//		$chain = new \SocialStream\Post\Thumbnail\Chain(null, $file->getFilename());
//		$this->getProduct()->setThumbnail($chain->getResult());
    }


    /**
     * Get the title and description out of the homepage.
     *
     * @param Category $homepage
     */
    private function spawnMetadataFromHomepage($homepage)
    {
        $this->getProduct()->setExternalTitle($homepage->getName());
        $this->getProduct()->setExternalBody('');
    }

    /**
     * A homepage's icon will be a homepage icon, or a folder if it's not a
     * visitable homepage.
     *
     * @param Category $homepage
     */
    private function spawnThumbnailFromHomepage($homepage)
    {
        $thumbnailUrl = 'icon:folder.png';
        if ($homepage->hasHomepage()) {
            $thumbnailUrl = 'icon:home.png';
        }
        $this->getProduct()->setThumbnail($thumbnailUrl);
    }

    /* Getters/Setters
	 */

    /**
     * Set type
     *
     * @param string $type
     */
    private function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get type
     *
     * @return string
     */
    private function getType()
    {
        return $this->type;
    }

    /**
     * Set product
     *
     * @param \SocialStream_Post_Abstract $product
     */
    private function setProduct($product)
    {
        $this->product = $product;
    }

    /**
     * Get product
     *
     * @return \SocialStream_Post_Abstract
     */
    private function getProduct()
    {
        return $this->product;
    }

    /**
     * Set url detection
     *
     * @param UrlDetection $urlDetection
     */
    private function setUrlDetection($urlDetection)
    {
        $this->urlDetection = $urlDetection;
    }

    /**
     * Get url detection
     *
     * @return UrlDetection
     */
    private function getUrlDetection()
    {
        return $this->urlDetection;
    }
}
