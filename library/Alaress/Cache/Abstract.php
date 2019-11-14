<?php

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException as RequestException;

/**
 * Alaress_Cache_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
     * The headers to be supplied when making a request
     * @var array
     */
    private $headers = [];

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
    protected $lockFile = '';

    /**
     * Path to a cached version of the url stream
     *
     * @var string
     */
    protected $cachedFile = '';


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
    public function __construct()
    {
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
     * @param string     $url
     * @param string[]  $headers
     * @return self
     * @return self
     * @throws \InvalidArgumentException
     */
    public function init(string $url, array $headers = []): \Alaress_Cache_Abstract
    {
        if ('' === trim($url)) {
            throw new \InvalidArgumentException('Empty url provided');
        }

        $this->url = $url;

        $this->headers = $headers;

        /* Logging class handles verbosity and any logging
         */
        // $this->registerObserver('Alaress_Cache_Locker_Log');

        /* Determine the filenames of files that could be used
         */
        $this->cachedFile = $this->generateCachedFilename();
        $this->lockFile = $this->generateLockFilename();

        /* Retrieve stream contents
         */
        $this->retrieveStreamContents();

        return $this;
    }


    /**
     * Get contents of a stream using a lockfile-based mechanism
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function retrieveStreamContents(): void
    {
        /* If the cached file exists, check its expiry
         */
        if (file_exists($this->cachedFile)) {
            if ($this->isExpired($this->cachedFile, $this->cachedTime)) {
                if (file_exists($this->lockFile)) {
                    if ($this->isExpired($this->lockFile, $this->lockTime)) {

                        /* Update cached version
                         */
                        //$this->notifyObservers("Cache expired, lockfile expired perhaps due to timeout or halted. Regenerate everything.");
                        $this->newCacheUpdate();
                    } else {
                        /* Load content from cached version
                         */
                        //$this->notifyObservers("Cache expired, lockfile still fresh. Loading from cache: {$this->cachedFile}, while in process of retrieving new stream contents.");
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
                //$this->notifyObservers("Cached file exists and is still fresh. Loading from cache: {$this->cachedFile}");
            }
        } else {
            /* First load
             */
            $this->newCacheUpdate();
        }
    }


    /**
     * Sam's new cache update :P
     *
     * @return Alaress_Cache_Abstract
     * @throws InvalidArgumentException
     */
    protected function newCacheUpdate(): \Alaress_Cache_Abstract
    {

        /* Remove lock files for this stream url
         */
        //$this->notifyObservers('Removing lock file.');
        $this->remove($this->lockFile);

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
        $this->remove($this->lockFile);

        return $this;
    }

    /**
     * Save stream contents from the url
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    protected function saveStreamContents(): bool
    {
        /* For images that existed in Storage since fopen can't
         * open relative urls
         */
        if (preg_match('#^/#', $this->url)) {
            $url = "http://{$_SERVER['HTTP_HOST']}{$this->url}";
        } else {
            $url = $this->url;
        }

        $httpClient = new GuzzleClient(['curl' => Proxy\Server::getInstance()->buildCurlOptions()]);

        try {
            $headers = ['User-Agent' => 'Mozilla/5.0 (Schoolbox)'];
            if (count($this->headers)) {
                $headers = array_merge($this->headers, $headers);
            }
            $httpClient->get($url, ['headers' => $headers, 'save_to' => $this->cachedFile]);
        } catch (RequestException $e) {
            return false;
        }

        return true;
    }

    /**
     * Create an empty file to indicate that a stream retrieval is in progress
     *
     * @return void
     */
    private function createLockFile(): void
    {
        $filehandle = @fopen($this->lockFile, 'w');
        fclose($filehandle);
    }

    /**
     * Remove the cached file
     *
     * @param $file
     * @return Alaress_Cache_Abstract
     */
    private function remove($file): \Alaress_Cache_Abstract
    {
        if (file_exists($file)) {
            @unlink($file);
        }

        return $this;
    }

    /**
     * Determine if the cached file has expired
     *
     * @param $file
     * @param $duration
     * @return bool
     */
    public function isExpired($file, $duration): bool
    {
        $now = $_SERVER['REQUEST_TIME'];

        /* Difference between now and when file was created
         */
        $difference = $now - filemtime($file);

        /* How much time is remaining
         */
        $remaining = $duration - $difference;

        //$this->notifyObservers(get_class($this) . ' file expires in ' . $remaining . ' seconds.');
        //$this->notifyObservers('[' . $difference . '] ' . $now . ' - ' . filemtime($file));

        if (filemtime($file) < ($now - $duration)) {
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
    protected function generateCachedFilename(): string
    {
        return Config::get('stream_dir') . '/' . $this->cacheFilePrefix . '_' . $this->encode($this->url) . '.txt';
    }

    /**
     * Figger out what the lock filename should be
     *
     * @return string
     */
    protected function generateLockFilename(): string
    {
        return Config::get('stream_dir') . '/' . $this->cacheFilePrefix . '_' . $this->encode($this->url) . '_lock.txt';
    }

    /**
     * Hashing method. By default it uses md5, but subclasses can use whatever
     * hashing method they want such as sha1
     *
     * @param $data
     * @return string
     */
    protected function encode($data): string
    {
        return md5($data);
    }

    /**
     * Add an observer
     *
     * @param $observer
     * @return Alaress_Cache_Abstract
     */
    private function registerObserver($observer): Alaress_Cache_Abstract
    {
        $this->observerRegistry[$observer] = new $observer($this->getVerbose());
    }

    /**
     * Remove an observer
     *
     * @param $observer
     * @return void
     */
    private function unregisterObserver($observer): void
    {
        unset($this->observerRegistry[$observer]);
    }

    /**
     * All observers - do something with data
     *
     * @param $text
     * @return void
     */
    protected function notifyObservers($text): void
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
     * Get cached file
     *
     * @return string
     */
    public function getCachedFile(): string
    {
        return $this->cachedFile;
    }

    /**
     * Set cached time
     *
     * @param null $cachedTime
     * @return \Alaress_Cache_Abstract
     */
    public function setCachedTime($cachedTime): \Alaress_Cache_Abstract
    {
        $this->notifyObservers("Setting cached duration to $cachedTime");

        $this->cachedTime = $cachedTime;

        return $this;
    }

    /**
     * Get cached time
     *
     * @return null|int void
     */
    public function getCachedTime(): ?int
    {
        return $this->cachedTime;
    }


    /**
     * Get stream contents
     *
     * @return string
     */
    public function getStreamContents(): ?string
    {
        return file_exists($this->cachedFile) ? file_get_contents($this->cachedFile) : null;
    }


    /**
     * Set verbose
     *
     * @param boolean $verbose
     * @return \Alaress_Cache_Abstract
     */
    public function setVerbose($verbose): \Alaress_Cache_Abstract
    {
        $this->verbose = $verbose;

        return $this;
    }

    /**
     * Get verbose
     *
     * @return boolean
     */
    public function getVerbose(): bool
    {
        return $this->verbose;
    }

    /**
     * Set lock time
     *
     * @param int $lockTime
     * @return \Alaress_Cache_Abstract
     */
    public function setLockTime($lockTime): \Alaress_Cache_Abstract
    {
        $this->notifyObservers("Setting locked duration to $lockTime");

        $this->lockTime = $lockTime;

        return $this;
    }


    /**
     * Get observer registry
     *
     * @return array
     */
    private function getObserverRegistry(): array
    {
        return $this->observerRegistry;
    }

    /**
     * Clear cache file, if you dont like it and you would like it to be refetched,
     * be careful as this could endlessly request the file
     *
     * @return void
     */
    public function invalidateCache(): void
    {
        $this->remove($this->cachedFile);
    }


}

