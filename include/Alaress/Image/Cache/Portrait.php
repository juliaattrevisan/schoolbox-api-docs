<?php
/**
 * Alaress_Image_Cache_Schoolbox
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Image_Cache_Portrait implements Alaress_Image_Cache_Interface {

    /* The image to load
      */
    private $filename = null;

    /* The $key is a way to uniquely associate a cached image with a particular
      * series of transformations applied to it. In this caching implementation,
      * it's the fileID concatenated with the name of the transformation applied.
      *
      * e.g. 51-Web.dat
      */
    private $key = null;

    /* This is where cached images are stored
      */
    private $cacheLocation = '';

    /* The filepath for processed image output
      * Defaults to /tmp if it couldn't figure out where the file goes
      */
    private $cachedFileLocation = '/tmp';

    /* Remember whether we loaded from the cached file. If it was loaded from the
      * cache, we can skip transformations as we assume they have been done in the past.
      * Otherwise we would be re-transforming an already-transformed image repeatedly
      */
    private $loadedFromCache = false;

    /* Placeholder image file
      */
    private $placeholder = '';

    /* External user id
      */
    private $externalUserId = -1;

    /* Handlers -_-
      */
    private $externalHandler = null;
    private $groupHandler = null;
    protected $userHandler = null;

    /**
     * Schoolbox user ID (as opposed to external ID)
     */
    protected $user_id = null;

    /**
     * Constructor
     *
     * Remembers what image you are trying to load from cache
     *
     * @param $externalUserId
     * @param $key
     */
    public function __construct($externalUserId, $key) {
        $this->setExternalUserId($externalUserId);
        $this->setKey($key);

        $this->setUserHandler(new User());
        $this->setExternalHandler(new External_User());
        $this->setGroupHandler(new Group());

        $this->setPlaceholder(WEB_root . '/images/core/placeholder.png');
        $this->setCacheLocation(Cfg::get('stream_dir').'/portrait');

        $this->decidePlaceholder();
    }

    /**
     * Decide whether or not to use a placeholder
     */
    private function decidePlaceholder() {

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
                $portraitPath = $portraitPathExists;
            }

        }

        $this->setFilename($portraitPath);
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
        $user_id = $this->getUserId();
        if ($_SESSION['id'] == $user_id) {
            return true;
        }

        /* Staff members can always see all portraits
           */
        if ($this->getGroupHandler()->isStaff()) {
            return true;
        }

        //
        // Student photos:
        // always visible by their parents
        // visible by other students if photos are not protected.
        if ($this->getGroupHandler()->isParentOfChild($user_id)) {
            return true;
        }
        if ($this->getGroupHandler()->isStudent($user_id)) {
            if (!Cfg::get('protect_student_photo')
                && $this->getGroupHandler()->isStudent()
            ) {
                return true;
            }
        }

        //
        // Staff photos:
        // visible by students and parents, if staff photos aren't protected.
        if ($this->getGroupHandler()->isStaff($user_id)) {
            if (!Cfg::get('protect_teacher_photo')
                && ($this->getGroupHandler()->isStudent() || $this->getGroupHandler()->isParent())
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Load an image by an id
     *
     * @throws Exception_Schoolbox_NotFound if file isn't found
     * @return mixed
     */
    public function load() {
        if (file_exists($this->getFilename())) {
            $info = getimagesize($this->getFilename());

            $info['filepath'] = $this->getFilename();
            $info['filename'] = basename($this->getFilename());
            $info['size'] = filesize($this->getFilename());

            /* We do this step if we are using Filesystem Cache directly
             */

            /* If this particular transformation key existed at some point,
             * this is what it's name would be
             *
             * e.g. <id>-Web.dat, <id>-ChickenPox.dat, etc. where Web and ChickenPox
             * correspond to a series of transformations
             */
            $id = substr($info['filename'], 0, strrpos($info['filename'], '.'));

            $cachedFilename = $id . '-' . $this->getKey() . '.dat';

            /* This is where you'd find it in the filesystem if it existed previously
             */
            $this->setCachedFileLocation($this->getCacheLocation() . '/' . $cachedFilename);

            /* Load a preprocessed version directly from cache
             */
            if (file_exists($this->getCachedFileLocation())) {
                $this->setLoadedFromCache(true);

                $info = getimagesize($this->getCachedFileLocation());
                $info['filepath'] = $this->getCachedFileLocation();
                $info['filename'] = $cachedFilename;
                $info['size'] = filesize($this->getCachedFileLocation());
            }

            return $this->standardise($info);
        }
        else {
            throw new Exception_Schoolbox_NotFound(sprintf('Could not read file "%s"', $this->getFilename()));
        }
    }

    /**
     * Standard temporary interface
     *
     * @param $imageArray
     * @return array
     */
    public function standardise($imageArray) {
        return array(
            'fileID' => $imageArray['filename'],
            'filepath' => $imageArray['filepath'],
            'filename' => $imageArray['filename'],
            'mime' => $imageArray['mime'],
            'size' => $imageArray['size'],
        );
    }

    /**
     * Set loaded from cache
     * Enforce boolean
     *
     * @param $loadedFromCache
     */
    private function setLoadedFromCache($loadedFromCache) {
        if ($loadedFromCache === false) {
            $this->loadedFromCache = $loadedFromCache;
        } else {
            $this->loadedFromCache = true;
        }
    }

    /**
     * Find out whether we loaded from cache or not
     *
     * @return bool
     */
    public function isLoadedFromCache() {
        return $this->loadedFromCache;
    }

    /* Getters/Setters
      */

    /**
     * Set filename
     *
     * @param $filename
     */
    private function setFilename($filename) {
        $this->filename = $filename;
    }

    /**
     * Get filename
     *
     * @return null
     */
    private function getFilename() {
        return $this->filename;
    }

    /**
     * Set key
     *
     * @param $key
     */
    private function setKey($key) {
        $this->key = $key;
    }

    /**
     * Get key
     *
     * @return null
     */
    public function getKey() {
        return $this->key;
    }

    /**
     * Set cache location
     *
     * @param $cacheLocation
     */
    private function setCacheLocation($cacheLocation) {
        $this->cacheLocation = $cacheLocation;
    }

    /**
     * Get cache location
     *
     * @return string
     */
    private function getCacheLocation() {
        return $this->cacheLocation;
    }

    /**
     * Set cached file location
     *
     * @param $cachedFileLocation
     */
    private function setCachedFileLocation($cachedFileLocation) {
        $this->cachedFileLocation = $cachedFileLocation;
    }

    /**
     * Get cached file location
     *
     * @return string
     */
    public function getCachedFileLocation() {
        return $this->cachedFileLocation;
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
     * Get user id
     *
     * @return mixed
     */
    private function getUserId() {
        if (!isset($this->user_id)) {
            $user_details = $this->getUserHandler()->getBySynergy($this->getExternalUserId());
            $this->user_id = $user_details['uid'];
        }
        return $this->user_id;
    }

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
     * Sets Schoolbox user handler, required for retrieving user's ID.
     *
     * @param $userHandler
     */
    public function setUserHandler($userHandler)
    {
        $this->userHandler = $userHandler;
    }

    /**
     * Gets Schoolbox user handler.
     *
     * @return User
     */
    public function getUserHandler()
    {
        return $this->userHandler;
    }

    /**
     * Get external id
     *
     * @return mixed
     */
    private function getExternalUserId() {
        return $this->externalUserId;
    }

    /**
     * Set external user id
     *
     * @param $externalUserId
     */
    private function setExternalUserId($externalUserId) {
        $this->externalUserId = $externalUserId;
    }
}
