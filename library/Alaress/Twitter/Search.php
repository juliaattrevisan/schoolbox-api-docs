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
    private $apiUrl = 'https://api.twitter.com/1.1/search/tweets.json?tweet_mode=extended';
    private $user = '';
    private $keywords = '';
    private $tweets = array();

    // This is just a default and is customised on a per-component basis in the component controllers
    // by setting it to whatever the user supplied in the component config
    private $maxTweets = 20;

    /** @var Alaress_Twitter_Filter_Search */
    private $filter = null;

    /**
     * Constructor
     *
     * @param $user
     * @param $keywords
     * @param Alaress_Twitter_Filter_Search $filter
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
     * @return array
     */
    protected function generateParameters()
    {
        $search = '';

        if (strlen($this->getUser())) {
            $search .= "from:{$this->getUser()} ";
        }

        $search .= urlencode(trim($this->getKeywords()));

        return ['q' => $search, 'count' => 100];
    }

    /**
     * Load tweets
     */
    public function load()
    {
        $processed = array();
        $params = $this->generateParameters();

        if (!empty($params)) {
            $cache = new Alaress_Twitter_Cache($params);
            $cache->init($this->apiUrl);
            $all_tweets = $cache->load();

            if (is_array($all_tweets)) {
                foreach ($all_tweets as $result) {

                    if ($result->possibly_sensitive ||
                        $result->retweeted_status->possibly_sensitive ||
                        $result->quoted_status->possibly_sensitive
                    ) {
                        // This might be not safe for work or children
                        continue;
                    }

                    // The Twitter API provides a filter that is supposed to hide sensitive tweets, filter:safe
                    // (see https://developer.twitter.com/en/docs/tweets/search/guides/standard-operators)
                    // Unfortunately this does not filter out quoted sensitive tweets, so to be certain we have to manually filter them out as below.
                    $tweetText = isset($result->retweeted_status) ?
                        $this->getFilter()->filterText($result->retweeted_status->full_text) :
                        $this->getFilter()->filterText($result->full_text);

                    // Text is in a different variable now; need to change it to full_text
                    if (isset($result->retweeted_status)) {
                        $processed[] = array(
                            'full_text'     => $tweetText,
                            'action'   => 'Retweeted by ' . $result->user->name,
                            'date'     => $result->created_at,
                            'fullname' => $result->retweeted_status->user->name,
                            'author'   => $result->retweeted_status->user->screen_name,
                            'avatar'   => str_replace('_normal', '_bigger', $result->retweeted_status->user->profile_image_url),
                            'media'    => $result->retweeted_status->extended_entities->media,
                            'id'       => $result->id
                        );
                    } else {
                        $processed[] = array(
                            'full_text'     => $tweetText,
                            'action'   => 'Tweeted',
                            'date'     => $result->created_at,
                            'fullname' => $result->user->name,
                            'author'   => $result->user->screen_name,
                            'avatar'   => str_replace('_normal', '_bigger', $result->user->profile_image_url),
                            'media'    => $result->extended_entities->media,
                            'id'       => $result->id
                        );
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
     * Set filter
     *
     * @param Alaress_Twitter_Filter_Search $filter
     */
    protected function setFilter($filter)
    {
        $this->filter = $filter;
    }

    /**
     * Get filter
     *
     * @return Alaress_Twitter_Filter_Search
     */
    protected function getFilter()
    {
        return $this->filter;
    }
}
