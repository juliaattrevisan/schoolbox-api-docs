<?php

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException as RequestException;

/**
 * Alaress_Cache_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Alaress_Cache_Abstract
{

    /**
     * The url of the stream you just got
     *
     * @var
     */
    protected $url = '';

    /**
     * A prefix for cached filenames
     *
     * @var string
     */
    protected $cacheFilePrefix = '';

    /**
     * The duration of a cached version before it becomes expired
     * This is a duration measured in seconds
     *
     * @var null
     */
    protected $cachedTime = 3600;

    /**
     * The duration of a lock file before it becomes expired
     * This is a duration measured in seconds
     *
     * @var int
     */
    protected $lockTime = 7200;

    /**
     * By default, $lockTime is a multiple of $cachedTime
     * but you can specify your own after instantiation
     *
     * @var int
     */
    protected $lockTimeMultiplier = 2;

    /**
     * Show plain debugging messages
     *
     * @var bool
     */
    protected $verbose = false;

    /**
     * The temporary lock file for the specified url stream
     *
     * @var string
     */
    private $lockFile = '';

    /**
     * A cached version of the url stream
     *
     * @var string
     */
    private $cachedFile = '';


    /* These are for logging purposes, using the Observer Design Pattern
     */

    /**
     * A list of all observers
     *
     * @var array
     */
    private $observerRegistry = array();

    /**
     * Constructor
     *
     * This is deliberately empty so you can instantiate an instance and set custom options
     * such as expiry durations, urls, etc
     */
    final public function __construct()
    {

        /* Example usage with multiple settings
        $cache = new Alaress_Cache_Locker_Abstract();

        $contents = $cache->setCachedTime(7200)
                        ->setLockTime(3600)
                        ->setVerbose(true)
                        ->init('http://www.google.com')
                        ->getStreamContents();
        */

        /* Allow subclasses to specify their own settings
         */
        $this->setup();
    }

    /**
     * Allow subclasses to specify their own settings
     *
     * @return mixed
     */
    abstract protected function setup();

    /**
     * Setup method
     *
     * @param string $url
     * @return self
     * @param string $params
     * @return $this
     */
    public function init($url, $params = "")
    {
        $this->setUrl($url);

        /* Logging class handles verbosity and any logging
         */
        // $this->registerObserver('Alaress_Cache_Locker_Log');

        /* Determine the filenames of files that could be used
         */
        $this->setCachedFile($this->generateCachedFilename());
        $this->setLockFile($this->generateLockFilename());

        /* Retrieve stream contents
         */
        $this->retrieveStreamContents();

        return $this;
    }

    /**
     * Get contents of a stream using a lockfile-based mechanism
     */
    protected function retrieveStreamContents()
    {

        /* If the cached file exists, check its expiry
         */
        if (file_exists($this->getCachedFile())) {
            if ($this->isExpired($this->getCachedFile(), $this->getCachedTime())) {
                if (file_exists($this->getLockFile())) {
                    if ($this->isExpired($this->getLockFile(), $this->getLockTime())) {

                        /* Update cached version
                         */
                        //$this->notifyObservers("Cache expired, lockfile expired perhaps due to timeout or halted. Regenerate everything.");
                        $this->newCacheUpdate();
                    } else {
                        /* Load content from cached version
                         */
                        //$this->notifyObservers("Cache expired, lockfile still fresh. Loading from cache: {$this->getCachedFile()}, while in process of retrieving new stream contents.");
                    }
                } else {
                    /* Update cached version
                     */
                    //$this->notifyObservers("Cache expired, lockfile doesn't exist. Regenerate everything.");
                    $this->newCacheUpdate();
                }
            } else {
                /* Load content from cached version
                 */
                //$this->notifyObservers("Cached file exists and is still fresh. Loading from cache: {$this->getCachedFile()}");
            }
        } /* First load
		 */
        else {
            $this->newCacheUpdate();
        }
    }

    /**
     * Sam's new cache update :P
     *
     * @return Alaress_Cache_Abstract
     */
    protected function newCacheUpdate()
    {

        /* Remove lock files for this stream url
         */
        //$this->notifyObservers('Removing lock file.');
        $this->remove($this->getLockFile());

        /* Create new lock file for this stream url
         */
        //$this->notifyObservers('Creating lock file.');
        $this->createLockFile();

        /* Fetch new content from stream url
         * Save content to cached file
         */
        //$this->notifyObservers('Saving stream contents to cache file.');
        $this->saveStreamContents();

        /* Remove lock file for this stream url
         */
        //$this->notifyObservers('Removing lock file.');
        $this->remove($this->getLockFile());

        return $this;
    }

    /**
     * Save stream contents from the url
     *
     * @return bool
     */
    protected function saveStreamContents()
    {
        /* For images that existed in Storage since fopen can't
         * open relative urls
         */
        if (preg_match('#^/#', $this->getUrl())) {
            $url = "http://{$_SERVER['HTTP_HOST']}{$this->getUrl()}";
        } else {
            $url = $this->getUrl();
        }

        $httpClient = new GuzzleClient(['curl' => Proxy\Server::getInstance()->guzzle_init()]);

        try {
            $httpClient->get($url, ['headers' => ['User-Agent' => 'Mozilla/5.0 (Schoolbox)'], 'save_to' => $this->getCachedFile()]);
        } catch (RequestException $e) {
            return false;
        }
    }

    /**
     * Create an empty file to indicate that a stream retrieval is in progress
     */
    private function createLockFile()
    {
        $filehandle = @fopen($this->getLockFile(), 'w');
        fclose($filehandle);
    }

    /**
     * Remove the cached file
     *
     * @param $file
     * @return Alaress_Cache_Abstract
     */
    private function remove($file)
    {
        @unlink($file);

        return $this;
    }

    /**
     * Determine if the cached file has expired
     *
     * @param $file
     * @param $duration
     * @return bool
     */
    public function isExpired($file, $duration)
    {
        $now = $_SERVER['REQUEST_TIME'];

        /* Difference between now and when file was created
         */
        $difference = $now - filectime($file);

        /* How much time is remaining
         */
        $remaining = $duration - $difference;

        //$this->notifyObservers(get_class($this) . ' file expires in ' . $remaining . ' seconds.');
        //$this->notifyObservers('[' . $difference . '] ' . $now . ' - ' . filectime($file));

        if (filectime($file) < ($now - $duration)) {
            //$this->notifyObservers($file . ' expired.');
            return true;
        }

        return false;
    }

    /**
     * Figger out what the cached filename should be
     *
     * @return string
     */
    protected function generateCachedFilename()
    {
        return Cfg::get('stream_dir') . '/' . $this->getCacheFilePrefix() . '_' . $this->encode($this->getUrl()) . '.txt';
    }

    /**
     * Figger out what the lock filename should be
     *
     * @return string
     */
    protected function generateLockFilename()
    {
        return Cfg::get('stream_dir') . '/' . $this->getCacheFilePrefix() . '_' . $this->encode($this->getUrl()) . '_lock.txt';
    }

    /**
     * Hashing method. By default it uses md5, but subclasses can use whatever
     * hashing method they want such as sha1
     *
     * @param $data
     * @return string
     */
    protected function encode($data)
    {
        return md5($data);
    }

    /**
     * Add an observer
     *
     * @param $observer
     */
    private function registerObserver($observer)
    {
        $this->observerRegistry[$observer] = new $observer($this->getVerbose());
    }

    /**
     * Remove an observer
     *
     * @param $observer
     */
    private function unregisterObserver($observer)
    {
        unset($this->observerRegistry[$observer]);
    }

    /**
     * All observers - do something with data
     *
     * @param $text
     */
    protected function notifyObservers($text)
    {
        if (count($this->getObserverRegistry())) {
            foreach ($this->getObserverRegistry() as $key => $instance) {
                $instance->report($text);
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * Set cache file prefix
     *
     * @param string $cacheFilePrefix
     */
    protected function setCacheFilePrefix($cacheFilePrefix)
    {
        $this->cacheFilePrefix = $cacheFilePrefix;
    }

    /**
     * Get cache file prefix
     *
     * @return string
     */
    protected function getCacheFilePrefix()
    {
        return $this->cacheFilePrefix;
    }

    /**
     * Set cached file
     *
     * @param string $cachedFile
     */
    protected function setCachedFile($cachedFile)
    {
        $this->cachedFile = $cachedFile;
    }

    /**
     * Get cached file
     *
     * @return string
     */
    public function getCachedFile()
    {
        return $this->cachedFile;
    }

    /**
     * Set cached time
     *
     * @param null $cachedTime
     * @return \Alaress_Cache_Abstract
     */
    public function setCachedTime($cachedTime)
    {
        $this->notifyObservers("Setting cached duration to $cachedTime");

        $this->cachedTime = $cachedTime;

        return $this;
    }

    /**
     * Get cached time
     *
     * @return null
     */
    public function getCachedTime()
    {
        return $this->cachedTime;
    }

    /**
     * Set lock file
     *
     * @param string $lockFile
     */
    protected function setLockFile($lockFile)
    {
        $this->lockFile = $lockFile;
    }

    /**
     * Get lock file
     *
     * @return string
     */
    protected function getLockFile()
    {
        return $this->lockFile;
    }

    /**
     * Get stream contents
     *
     * @return string
     */
    public function getStreamContents()
    {
        return file_get_contents($this->getCachedFile());
    }

    /**
     * Set url
     *
     * @param  $url
     */
    protected function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set verbose
     *
     * @param boolean $verbose
     * @return \Alaress_Cache_Locker_Abstract
     */
    public function setVerbose($verbose)
    {
        $this->verbose = $verbose;

        return $this;
    }

    /**
     * Get verbose
     *
     * @return boolean
     */
    public function getVerbose()
    {
        return $this->verbose;
    }

    /**
     * Set lock time
     *
     * @param int $lockTime
     * @return \Alaress_Cache_Locker_Abstract
     */
    public function setLockTime($lockTime)
    {
        $this->notifyObservers("Setting locked duration to $lockTime");

        $this->lockTime = $lockTime;

        return $this;
    }

    /**
     * Get lock time
     *
     * @return int
     */
    public function getLockTime()
    {
        return $this->lockTime;
    }

    /**
     * Set lock time multiplier
     *
     * @param int $lockTimeMultiplier
     */
    protected function setLockTimeMultiplier($lockTimeMultiplier)
    {
        $this->lockTimeMultiplier = $lockTimeMultiplier;
    }

    /**
     * Get lock time multiplier
     *
     * @return int
     */
    protected function getLockTimeMultiplier()
    {
        return $this->lockTimeMultiplier;
    }

    /**
     * Get observer registry
     *
     * @return array
     */
    private function getObserverRegistry()
    {
        return $this->observerRegistry;
    }

    /**
     * Clear cache file, if you dont like it and you would like it to be refetched,
     * be careful as this could endlessly request the file
     *
     */
    public function invalidateCache()
    {
        $this->remove($this->getCachedFile());
    }
}

