<?php
/**
 * Alaress_Twitter_Cache
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Twitter_Cache extends Alaress_Cache_Abstract {

    /**
     * A prefix for cached filenames
     *
     * @var string
     */
    protected $cacheFilePrefix = 'twitter';

    /**
     * Contains specific URL parms for twitter requests
     *
     * @var array
     */
    protected $parameters;

    /**
     * Hook method
     *
     * @return mixed|void
     */
    protected function setup() {
        $this->setCachedTime(Cfg::get('twitter_api_caching'));
        $this->setLockTime($this->getLockTimeMultiplier() * $this->getCachedTime());
    }


    /**
     * @param string     $url
     * @param null|array $params
     * @return $this
     * @throws InvalidArgumentException
     */
    public function init(string $url, ?array $params = null): Alaress_Cache_Abstract {
        $this->setParameters($params);
        parent::init($url, $params);
        return $this;
    }

    /**
     * Overwrites native cache stream saver with OAuth fetch system
     */
    protected function saveStreamContents(): \bool {
        $twitterCon = new Alaress_Twitter_Loader_OAuth(
            Cfg::get('twitter_api_consumer_key'),
            Cfg::get('twitter_api_consumer_secret'),
            Cfg::get('twitter_api_oauth_token'),
            Cfg::get('twitter_api_oauth_token_secret')
        );

        $result = $twitterCon->load($this->getUrl(), $this->getParameters());
        file_put_contents($this->getCachedFile(), serialize($result));

        return true;
    }

    /**
     * Load function for converting cached data into useable data array
     *
     * @return array
     */
    public function load(): array
    {
        return unserialize($this->getStreamContents());
    }

    /**
     * Figger out what the cached filename should be
     *
     * @return string
     */
    protected function generateCachedFilename(): string
    {
        return Cfg::get('stream_dir') . '/' . $this->getCacheFilePrefix() . '_' . $this->encode($this->getUrl().serialize($this->getParameters())) . '.txt';
    }

    /**
     * Figger out what the lock filename should be
     *
     * @return string
     */
    protected function generateLockFilename(): string
    {
        return Cfg::get('stream_dir') . '/' . $this->getCacheFilePrefix() . '_' . $this->encode($this->getUrl().serialize($this->getParameters())) . '_lock.txt';
    }

    /**
     * Get for parameters
     *
     * @return array
     */
    protected function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Setter for params
     *
     * @vars array
     * @param array $params
     * @return self
     */
    protected function setParameters(array $params): \Alaress_Twitter_Cache
    {
        $this->parameters = $params;
        return $this;
    }
}
