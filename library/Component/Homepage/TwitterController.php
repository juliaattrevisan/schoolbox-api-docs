<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Zend\Uri\UriFactory;
use \Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_TwitterController extends Component_Homepage_ControllerAbstract
{
    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'twitter')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'twitter_feed', 'twitter_feed_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');
        $this->setBlock('twitter_feed', 'tweet', 'tweet_block');
        $this->setBlock('tweet', 'tweet_media', 'tweet_media_block');
        $this->setVar('tweet_block', '');

        $emptyState = false;
        $noApiKey = false;
        $noConfig = false;

        if (
            strlen(Config::get('twitter_api_consumer_key')) == 0
            || strlen(Config::get('twitter_api_consumer_secret')) == 0
            || strlen(Config::get('twitter_api_oauth_token')) == 0
            || strlen(Config::get('twitter_api_oauth_token_secret')) == 0
        ) {
            $emptyState = true;
            $noApiKey = true;
        }

        /**
         * Check if any username or hashtag has been set
         */
        if ($this->getConfig('username') == '' && $this->getConfig('keywords') == '') {
            $emptyState = true;
            $noConfig = true;
        }

        /**
         * Search for tweets
         */
        $allTweets = new Alaress_Twitter_Search($this->getConfig('username'), $this->getConfig('keywords'));
        $allTweets->setMaxTweets($this->getConfig('size'))->load();
        $allResults = $allTweets->getTweets();

        if (empty($allResults)) {
            $emptyState = true;
        }

        /**
         * Show empty state or show content - that is the if statement
         */
        if ($emptyState) {

            $this->setBlock('empty_state', 'empty_api', 'empty_api_block');
            $this->setBlock('empty_state', 'empty_api_admin', 'empty_api_admin_block');
            $this->setBlock('empty_state', 'empty_config', 'empty_config_block');
            $this->setBlock('empty_state', 'empty_config_write', 'empty_config_write_block');
            $this->setBlock('empty_state', 'empty_results', 'empty_results_block');
            $this->setBlock('empty_state', 'empty_results_write', 'empty_results_write_block');

            $this->setVar([
                'empty_api_block' => '',
                'empty_api_admin_block' => '',
                'twitter_feed_block' => '',
                'empty_config_block' => '',
                'empty_config_write_block' => '',
                'empty_results_block' => '',
                'empty_results_write_block' => '',
            ]);

            if ($noApiKey) {

                if ($this->getUser()->isSuperuser()) {
                    $this->parse('empty_api_admin_block', 'empty_api_admin');
                } else {
                    $this->parse('empty_api_block', 'empty_api');
                }

            } elseif ($noConfig) {

                if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                    $this->parse('empty_config_block', 'empty_config');
                } else {
                    $this->parse('empty_config_write_block', 'empty_config_write');
                }

            } else {

                if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                    $this->parse('empty_results_block', 'empty_results');
                } else {
                    $this->parse('empty_results_write_block', 'empty_results_write');
                }
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            $this->setVar('empty_state_block', '');

            $i = 0;
            foreach ($allResults as $currentTweet) {
                $date  = new Alaress_Date_Format(strtotime($currentTweet['date']), 'U');

                $avatarUrl = UriFactory::factory($currentTweet['avatar']);
                $avatarUrl->setScheme(null);
                $this->setVar([
                    'TWEET_TEXT'     => Scrub::html($currentTweet['text']),
                    'TWEET_DATE'     => $date->getShort(),
                    'TWEET_AUTHOR'   => Scrub::text($currentTweet['author']),
                    'TWEET_FULLNAME' => Scrub::text($currentTweet['fullname']),
                    'TWEET_AVATAR'   => Scrub::text($avatarUrl)
                ]);

                if (is_array($currentTweet['media']) && count($currentTweet['media'])) {

                    foreach ($currentTweet['media'] as $currentMedia) {
                        $this->setVar('TWEET_MEDIA_URL', $currentMedia->media_url_https);
                        $this->parse('tweet_media_block', 'tweet_media', true);
                    }

                } else {
                    $this->setVar('tweet_media_block', '');
                }

                $this->parse('tweet_block', 'tweet', true);

                /* Clear children after parsing out the parent
                 */
                $this->setVar('tweet_media_block', '');
            }

            $this->parse('twitter_feed_block', 'twitter_feed');
        }

        /**
         * Build Twitter Search URL
         */
        $q = 'https://twitter.com/search?q=';
        $q .= urlencode($this->getConfig('username'));
        if ($this->getConfig('username') && $this->getConfig('keywords')) {
            $q .= '%20';
        }
        $q .= urlencode($this->getConfig('keywords'));

        $this->setVar('SEARCH_URL', $q);

        return true;
    }
}
