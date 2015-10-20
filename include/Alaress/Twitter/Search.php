<?php

/**
 * Alaress_Twitter_Search
 *
 * Just like Alaress_Twitter_Search, but for the 1.1 API.
 *
 * You cannot use the Search API to find Tweets older than about a week.
 */
class Alaress_Twitter_Search
{
    private $searchPrefix = 'https://api.twitter.com/1.1/search/tweets.json?q=';
    private $user = '';
    private $searchUrl = '';
    private $keywords = '';
    private $tweets = array();
    private $maxTweets = 20;
    private $filter = null;

    /**
     * Constructor
     *
     * @param $user
     * @param $keywords
     * @param null $filter
     */
    public function __construct($user, $keywords, $filter = null)
    {
        $this->setUser(trim($user));
        $this->setKeywords(trim($keywords));

        /* Filter object for tweet content. Activates urls, etc. or you can pass
         * or own custom instance.
         */
        if ($filter === null) {
            $this->setFilter(new Alaress_Twitter_Filter_Search());
        } else {
            $this->setFilter($filter);
        }
    }

    /**
     * Generate query string params (this function generates them as an array)
     *
     * @return string
     */
    protected function generateParameters()
    {
        $search = '';

        if (strlen($this->getUser())) {
            $search .= "from:{$this->getUser()} ";
        }

        $search .= urlencode(trim($this->getKeywords()));

        return array('q' => $search);
    }

    /**
     * Load tweets
     */
    public function load()
    {
        $processed = array();
        $resource = 'https://api.twitter.com/1.1/search/tweets.json';
        $params = $this->generateParameters();

        if (!empty($params)) {
            $cache = new Alaress_Twitter_Cache();
            $cache->init($resource, $params);

            $all_tweets = $cache->load();
            $tweetText = array();

            if (is_array($all_tweets)) {
                foreach ($all_tweets as $result) {
                    if ($result->possibly_sensitive) {
                        // This might be not safe for work or children
                        continue;
                    }
                    if (!in_array($result->text, $tweetText)) {
                        $processed[] = array(
                            'text'     => $this->getFilter()->filterText($result->text),
                            'date'     => $result->created_at,
                            'fullname' => $result->user->name,
                            'author'   => $result->user->screen_name,
                            'avatar'   => $result->user->profile_image_url,
                            'media'    => $result->entities->media,
                        );
                        $tweetText[] = $result->text;
                    }
                }


                if ($this->getMaxTweets() && is_numeric($this->getMaxTweets())) {
                    $processed = array_slice($processed, 0, $this->getMaxTweets());
                }

            }
        }

        $this->setTweets($processed);
    }

    /* Getters/Setters
     */

    /**
     * Get tweets
     *
     * @return array
     */
    public function getTweets()
    {
        return $this->tweets;
    }

    /**
     * Set tweets
     *
     * @param $tweets
     */
    protected function setTweets($tweets)
    {
        $this->tweets = $tweets;
    }

    /**
     * Set keywords
     *
     * @param $keywords
     */
    public function setKeywords($keywords)
    {
        $this->keywords = $keywords;
    }

    /**
     * Get keywords
     *
     * @return string
     */
    protected function getKeywords()
    {
        return $this->keywords;
    }

    /**
     * Set search url
     *
     * @param $searchUrl
     */
    protected function setSearchUrl($searchUrl)
    {
        $this->searchUrl = $searchUrl;
    }

    /**
     * Get search url
     *
     * @return string
     */
    protected function getSearchUrl()
    {
        return $this->searchUrl;
    }

    /**
     * Set user
     *
     * @param $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Get user
     *
     * @return string
     */
    protected function getUser()
    {
        return $this->user;
    }

    /**
     * Set the max number of tweets to preprocess
     *
     * Chainable method
     *
     * @param  $maxTweets
     * @return self
     */
    public function setMaxTweets($maxTweets)
    {
        $this->maxTweets = $maxTweets;

        return $this;
    }

    /**
     * Get the max number of tweets to preprocess
     *
     * @return int
     */
    protected function getMaxTweets()
    {
        return $this->maxTweets;
    }

    /**
     * Get search prefix
     *
     * @return string
     */
    protected function getSearchPrefix()
    {
        return $this->searchPrefix;
    }

    /**
     * Set filter
     *
     * @param $filter
     */
    protected function setFilter($filter)
    {
        $this->filter = $filter;
    }

    /**
     * Get filter
     *
     * @return null
     */
    protected function getFilter()
    {
        return $this->filter;
    }
}
