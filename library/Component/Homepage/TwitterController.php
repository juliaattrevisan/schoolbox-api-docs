<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Zend\Uri\UriFactory;

class Component_Homepage_TwitterController extends Component_Homepage_ControllerAbstract
{

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {

    }

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

        $this->setBlock($templateHandle, 'tweet', 'tweet_block');
        $this->setBlock('tweet', 'tweet_media', 'tweet_media_block');

        $allTweets = new Alaress_Twitter_Search($this->getConfig('username'), $this->getConfig('keywords'));
        $allTweets->setMaxTweets($this->getConfig('size'))->load();

        $allResults = $allTweets->getTweets();

        // Render out tweets
        $this->setVar('tweet_block', '');

        if (count($allResults)) {
            $i = 0;

            foreach ($allResults as $currentTweet) {
                $color = ++$i % 2 ? '' : 'dark';
                $date  = new Alaress_Date_Format(strtotime($currentTweet['date']), 'U');

                $avatarUrl = UriFactory::factory($currentTweet['avatar']);
                $avatarUrl->setScheme(null);
                $this->setVar(
                    array(
                        'TWEET_TEXT'     => Scrub::html($currentTweet['text']),
                        'TWEET_DATE'     => $date->getShort(),
                        'TWEET_AUTHOR'   => Scrub::text($currentTweet['author']),
                        'TWEET_FULLNAME' => Scrub::text($currentTweet['fullname']),
                        'TWEET_AVATAR'   => Scrub::text($avatarUrl),
                        'ON_OFF'         => $color,
                    )
                );

                if (is_array($currentTweet['media']) && count($currentTweet['media'])) {
                    foreach ($currentTweet['media'] as $currentMedia) {
                        $this->setVar(
                            array(
                                'TWEET_MEDIA_URL' => $currentMedia->media_url_https,
                            )
                        );

                        $this->parse('tweet_media_block', 'tweet_media', true);
                    }
                } else {
                    $this->setVar(
                        array(
                            'tweet_media_block' => ''
                        )
                    );
                }

                $this->parse('tweet_block', 'tweet', true);

                /* Clear children after parsing out the parent
                 */
                $this->setVar(
                    array(
                        'tweet_media_block' => ''
                    )
                );
            }
        } else {
            $this->setVar(
                array(
                    'tweet_media_block' => ''
                )
            );
        }

        // Build Twitter Search URL
        $q = 'https://twitter.com/search?q=';
        $q .= urlencode($this->getConfig('username'));
        if ($this->getConfig('username') && $this->getConfig('keywords')) {
            $q .= '%20';
        }
        $q .= urlencode($this->getConfig('keywords'));

        $this->setVar(
            array(
                'SEARCH_URL' => $q
            )
        );

        return true;
    }
}
