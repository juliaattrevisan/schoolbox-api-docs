<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Post\Factory;

use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model;
use Schoolbox\Resource\Storage\Mapper;
use Schoolbox\Resource\Storage\Model as StorageModel;
use Schoolbox\User;
use Schoolbox\UserMapper;
use SocialStream\UrlDetection;

/**
 * Class Creational
 *
 * Created populated SocialStream Post objects for further
 * manipulation in controllers
 *
 * @package SocialStream\Post\Factory
 */
class Creational extends AbstractFactory
{
    private const EXTERNAL_TITLE_LENGTH = 255;

    /**
     * The object that was produced by the factory
     *
     * @var \SocialStream_Post_Abstract
     */
    private $product;


    /**
     * This class knows how to figure out which urls
     * are Storage and Resource ones
     *
     * @var UrlDetection
     */
    private $urlDetection;


    /**
     * Constructor
     *
     * @param string $url
     */
    public function __construct($url)
    {
        $this->setUrl(trim($url));
        $this->urlDetection = new UrlDetection($url);
    }


    /**
     * Produce an instance of SocialStream_Post_Abstract subclass
     * Populate it with the information from $_POST etc
     * Spawn metadata and thumbnails where appropriate
     *
     * @param array $data
     * @return \SocialStream_Post_Abstract
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function produce(array $data = null): \SocialStream_Post_Abstract
    {

        // if data contains an attachment
        if (\is_array($data['attachment'] ?? false)) {
            $factory = new CollectorFactory();
            try {
                // posts (should) only have one attachment
                /** @var StorageModel $storage */
                $storage = $factory->build($data['attachment'])->getFirst();
                $this->parseStorageMetadata($storage, $data);
            } catch (\Schoolbox\Resource\Exception $e) {
                // this means the user 'attached' a file that failed upload, but submitted the form anyway
                // default back to plaintext
                $this->product = $this->constructDefault();
                $this->populate($data);
            }
        } elseif ($this->urlDetection->isLocalUrl()) {
            /* Treat local urls differently as they should bypass the caching
             * stuff entirely - this applies to the Resource browser mainly,
             * but also when people input a local url manually
             */

            /* When a Resource url is provided
             */
            if ($this->urlDetection->isResourceUrl()) {
                $resource = $this->constructFromResource($this->getUrl());
                $this->populate($data);

                /* Spawn additional stuff
                 */
                $this->spawnMetadataFromResource($resource);
                $this->spawnThumbnailFromResource($resource);
            } elseif ($this->urlDetection->isHomepageUrl()) {
                // Homepage URLs: load the link from the website
                $homepage = $this->constructFromHomepage($this->getUrl());
                $this->populate($data);
                $this->spawnMetadataFromHomepage($homepage);
                $this->spawnThumbnailFromHomepage($homepage);
            } elseif ($this->urlDetection->isStorageUrl()) {
                /* When a Storage url is provided, manufacture metadata and thumbnail
                 * as if they had been uploaded
                 */
                $fileMapper = Mapper::getInstance();
                $file = $fileMapper->findByHash($this->urlDetection->getHash());

                $uploadedFile = $this->constructFromUpload($file);
                $this->populate($data);

                /* Spawn additional stuff
                 */
                $this->spawnMetadataFromUpload($uploadedFile);
                $this->spawnThumbnailFromUpload($uploadedFile);
            } elseif ($this->urlDetection->isWebdavUrl()) {
                // I guess just construct a standard URL post
                $this->product = new \SocialStream_Post_Url();
                $this->populate($data);
            }
        } elseif (($_FILES['file']['size'] ?? 0) > 0) {
            /* If upload option was chosen, extract metadata and thumbnail information
             * directly from already-known information (no curling needed)
             */
            $fileArray = $_FILES['file'];

            try {
                $resource = Mapper::getInstance()->accept($fileArray['name'], $fileArray['tmp_name']);
                $this->parseStorageMetadata($resource, $data);
            } catch (\Schoolbox\Mapper\Exception $e) {
                // noop - don't do anything else if you can attach the file for some reason?
            }
        } else {
            /* Try to process as a url instead
             */
            if ('' !== $this->getUrl()) {
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
                $curl = new \Alaress_Curl_Url($this->product->getUrl());

                $curl->addOptions([
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1500.95 Safari/537.36',
                ]);

                $curl->exec();

                /* Spawn additional stuff
                 *
                 * Optionally parse the curl result here into a dom parser
                 * That would allow for attribute gathering
                 */
                $this->spawnMetadataFromCurl($curl);
                $this->spawnThumbnailFromCurl($curl);
            } else {
                /* Plain text messsage
                 */
                $this->product = $this->constructDefault();
                $this->populate($data);
            }
        }
        return $this->product;
    }


    /**
     * @param StorageModel $storage
     * @param array        $data
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    protected function parseStorageMetadata(StorageModel $storage, array $data)
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
                $this->product = new \SocialStream_Post_Video();
                return;
            }
        }

        /* Check if we have been given a URL to an image */
        if ($filename = pathinfo(parse_url($this->getUrl(), PHP_URL_PATH), PATHINFO_BASENAME)) {
            try {
                $file = \Schoolbox\File\MimetypeMapper::getInstance()->getByFilename($filename);

                if ($file->isImage()) {
                    $this->product = new \SocialStream_Post_Image();
                    return;
                }
            } catch (\Schoolbox\File\Exception | \UnexpectedValueException $e) {
                // noop when something went wrong with the file
            }
        }

        /* Alternatively it's just a link to the internet
         */
        $this->product = new \SocialStream_Post_Url();
    }


    /**
     * Construct a post from a link by doing mimetype analysis
     *
     * @param StorageModel $resource
     * @return StorageModel
     */
    private function constructFromUpload(StorageModel $resource): StorageModel
    {

        /* Figure out if it's a video
         */
        if ($resource->getMimetype()->isVideo()) {
            $this->product = new \SocialStream_Post_Video();
        } elseif ($resource->getMimetype()->isImage()) {
            $this->product = new \SocialStream_Post_Image();
        } else {
            $this->product = new \SocialStream_Post_Url();
        }

        return $resource;
    }


    /**
     * Construct a post from an already-uploaded resource
     *
     * @param string $url
     * @return \Schoolbox\Resource\File\Model
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    private function constructFromResource(string $url): \Schoolbox\Resource\File\Model
    {
        $bits = parse_url($url);
        $query = $bits['query'];

        parse_str($query, $values);
        $fileId = $values['id'];

        $fileMapper = new \Schoolbox\Resource\File\Mapper();
        $file = $fileMapper->findById($fileId);

        if ($file->getMimetype()->isVideo()) {
            $this->product = new \SocialStream_Post_Video();
        } elseif ($file->getMimetype()->isImage()) {
            $this->product = new \SocialStream_Post_Image();
        } else {
            $this->product = new \SocialStream_Post_Url();
        }

        return $file;
    }


    /**
     * Construct a post from a homepage URL
     *
     * @param string $url
     * @return Folder
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    private function constructFromHomepage(string $url): Model
    {
        $this->product = new \SocialStream_Post_Url();

        $homepageId = $this->urlDetection->getHomepageIdFromUrl();

        return FolderMapper::getInstance()->findById($homepageId);
    }


    /**
     * If it was not a video, link to the internet, etc then assume it's
     * just a plain old text message
     *
     * @return \SocialStream_Post_String
     */
    private function constructDefault()
    {
        return new \SocialStream_Post_String();
    }


    /**
     * Puts data from the $data array into $object
     * This is usually the result of a post
     *
     * @param array $data
     */
    private function populate($data)
    {
        $this->product->setRootId($data['rootId']);
        $this->product->setParentId($data['parentId']);
        $this->product->setHomepageId($data['homepageId']);
        // Owner ID is probably passed as an int value, convert to actual user
        $owner = $data['ownerId'];
        if (!$owner instanceof User) {
            $userMapper = UserMapper::getInstance();
            try {
                $owner = $userMapper->findById($owner);
            } catch (\Schoolbox\Mapper\Exception $e) {
                // noop: retain blank user
            }
        }

        $this->product->setOwner($owner);
        $this->product->setBody(trim($data['body']));
        $this->product->setCreatedAt($data['requestTime']);
        $this->product->setUpdatedAt($data['requestTime']);

        /* This is set in a special way in socialstream/modifyForm.php
         */
        $this->product->setUrl($this->getUrl());
    }

    /* Spawn metadata and thumbnails via different ways
     */

    /**
     * Get the title and description
     *
     * @param \Alaress_Curl_Url $curl
     */
    private function spawnMetadataFromCurl($curl)
    {
        $chain = new \SocialStream\Post\Metadata\Chain($curl, $this->product->getUrl());
        $title = $chain->getResult()->getTitle();
        if (strlen($title) > self::EXTERNAL_TITLE_LENGTH) {
            $title = \TextFormatter::cutSentence($title, self::EXTERNAL_TITLE_LENGTH);
        }
        $this->product->setExternalTitle($title);
        $this->product->setExternalBody($chain->getResult()->getDescription());
    }


    /**
     * Use a two-layer caching setup for spawning thumbnail images
     * For images, thumbnails are obtained via a series of regexes
     *
     * @param \Alaress_Curl_Url $curl
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     */
    private function spawnThumbnailFromCurl($curl)
    {
        $chain = new \SocialStream\Post\Thumbnail\Chain($curl, $this->product->getUrl());

        /* To prevent a double-curl, remember the place where a thumbnail was
         * located (this is used as the cached image transform filename hash)
         *
         * Avoid caching icons
         */
        if ('' !== $chain->getResult()) {
            $this->product->setThumbnail($chain->getResult());

            /* Save the original in the cache location
             * but don't cache icons
             */
            if (!preg_match('/^icon:/', $chain->getResult())) {
                $cache = new \SocialStream\Cache\Original();
                $cache->initPost($this->product);

                /* Thumbnail metadata was found, but thumbnail itself
                 * isn't there (broken link)
                 */

                if (!$cache->hasThumbnail()) {
                    $this->product->setThumbnail('');
                }
            }
        }
    }


    /**
     * Get the title and description
     *
     * @param StorageModel $uploadedFile
     */
    private function spawnMetadataFromUpload(StorageModel $uploadedFile)
    {
        $this->product->setExternalTitle($uploadedFile->getBasename());
        $this->product->setExternalBody('');
    }


    /**
     * Get the thumbnail usage
     *
     * @param StorageModel $uploadedFile
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     */
    private function spawnThumbnailFromUpload(StorageModel $uploadedFile)
    {

        /* Set up video thumbnails
         */
        if ($uploadedFile->getMimetype()->isVideo()) {
            $url = "/cms/sendVideo.php?type=storage&id={$uploadedFile->getHash()}";

            $this->product->setUrl($url);
        } elseif ($uploadedFile->getMimetype()->isImage()) {
            /* Set up image thumbnails
             */
            $url = "/storage/image.php?size=maxsize&hash={$uploadedFile->getHash()}";

            $this->product->setThumbnail($url);
            $this->product->setUrl($url);

            /* Save the original in the cache location
             */
            $cache = new \SocialStream\Cache\Original();
            $cache->initPost($this->product);
        } else {
            /* Set up storage stream location and its icon thumbnails
             */
            $url = "/storage/fetch.php?hash={$uploadedFile->getHash()}";
            $this->product->setUrl($url);
            $this->product->setThumbnail('icon:' . $uploadedFile->getMimetype()->getIconFile());
        }
    }


    /**
     * Get the title and description
     *
     * @param \Schoolbox\Resource\File\Model $file
     */
    private function spawnMetadataFromResource($file)
    {
        $this->product->setExternalTitle($file->getFilename());
        $this->product->setExternalBody('');
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
     * @param \Schoolbox\Resource\File\Model $file
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     */
    private function spawnThumbnailFromResource($file)
    {

        /* Set up video thumbnails
         */
        if ($file->getMimetype()->isVideo()) {
            // noop
        } elseif ($file->getMimetype()->isImage()) {
            /* Set up image thumbnails
             */
            $url = "/send.php?id={$file->getId()}";

            $this->product->setThumbnail($url);
            $this->product->setUrl($url);

            /* Save the original in the cache location
             */
            $cache = new \SocialStream\Cache\Original();
            $cache->initPost($this->product);
        } else {
            /* Set up icon thumbnails
             */
            $url = "/send.php?id={$file->getId()}";
            $this->product->setUrl($url);
            $this->product->setThumbnail('icon:' . $file->getMimetype()->getIconFile());
        }
    }


    /**
     * Get the title and description out of the homepage.
     *
     * @param Folder $homepage
     */
    private function spawnMetadataFromHomepage($homepage)
    {
        $this->product->setExternalTitle($homepage->getName());
        $this->product->setExternalBody('');
    }


    /**
     * A homepage's icon will be the cover image, or nothing at all.
     *
     * @param Folder $homepage
     */
    private function spawnThumbnailFromHomepage($homepage)
    {
        $cover = $homepage->getCoverImage();
        if ($cover) {
            $this->product->setThumbnail($url = "/storage/image.php?size=square64&hash={$cover->getHash()}");
        }
    }
}
