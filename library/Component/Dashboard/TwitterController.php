<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Twitter\Loader\ValidOAuth;
use Zend\Uri\UriFactory;
use \Schoolbox\Permission\Access\UserAccess;

/**
 * Class Component_Dashboard_TwitterController
 */
class Component_Dashboard_TwitterController extends Component_Dashboard_ControllerAbstract {

    /**
     * Renders the data into the SitePage. Presentation logic.
     *
     * @param String $templateHandle
     * @return boolean
     * @throws InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidArgumentException
     * @throws \Zend\Uri\Exception\InvalidUriPartException
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
        $this->setBlock('tweet_media', 'tweet_media_thumbnails', 'tweet_media_thumbnails_block');
        $this->setBlock('tweet_media_thumbnails', 'media_photo', 'media_photo_block');
        $this->setBlock('tweet_media_thumbnails', 'media_video', 'media_video_block');
        $this->setVar('tweet_block', '');

        $emptyState = false;
        $noApiKey = false;
        $noConfig = false;

        /* Only display when all four pieces are provided
         */
        if (!(new ValidOAuth())->isSatisfiedBy(
            Config::get('twitter_api_consumer_key'),
            Config::get('twitter_api_consumer_secret'),
            Config::get('twitter_api_oauth_token'),
            Config::get('twitter_api_oauth_token_secret')
        )) {
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

            foreach ($allResults as $currentTweet) {
                $date  = new Alaress_Date_Format(strtotime($currentTweet['date']), 'U');

                $avatarUrl = UriFactory::factory($currentTweet['avatar']);
                $avatarUrl->setScheme(null);
                $this->setVar([
                    'TWEET_TEXT'     => Scrub::html($currentTweet['full_text']),
                    'TWEET_ACTION'   => Scrub::text($currentTweet['action']),
                    'TWEET_DATE'     => $date->getShort(),
                    'TWEET_AUTHOR'   => Scrub::text($currentTweet['author']),
                    'TWEET_FULLNAME' => Scrub::text($currentTweet['fullname']),
                    'TWEET_AVATAR'   => Scrub::text($avatarUrl),
                    'TWEET_ID'       => Scrub::text($currentTweet['id'])
                ]);

                if (is_array($currentTweet['media']) && count($currentTweet['media'])) {
                    foreach ($currentTweet['media'] as $currentMedia) {
                        $photoURL = $currentMedia->media_url_https;
                        $videoURL = $currentMedia->video_info->variants[2]->url;
                        if ($currentMedia->type === 'video') {
                            $this->setVar('TWEET_MEDIA_URL', $videoURL);
                            $this->parse('media_video_block', 'media_video');
                            $this->setVar('media_photo_block', '');
                        } else {
                            $this->setVar('TWEET_MEDIA_URL', $photoURL);
                            $this->parse('media_photo_block', 'media_photo');
                            $this->setVar('media_video_block', '');
                        }
                        $this->parse('tweet_media_thumbnails_block', 'tweet_media_thumbnails', true);
                    }
                    $this->parse('tweet_media_block', 'tweet_media', true);

                } else {
                    $this->setVar('tweet_media_block', '');
                    $this->setVar('tweet_media_thumbnails_block', '');
                }

                $this->parse('tweet_block', 'tweet', true);

                /* Clear children after parsing out the parent
                 */
                $this->setVar('tweet_media_block', '');
                $this->setVar('tweet_media_thumbnails_block', '');
            }

            /* Build Twitter Search URL per component
             */
            $this->setVar('SEARCH_URL', $allTweets->getSearchUrl());

            $this->parse('twitter_feed_block', 'twitter_feed');
        }

        return true;
    }
}
