<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_TwitterController extends Component_Homepage_ControllerAbstract {

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'twitter')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'tweet', 'tweet_block');

        $allTweets = new Alaress_Twitter_Search($this->getConfig('username'), $this->getConfig('keywords'));
        $allTweets->setMaxTweets($this->getConfig('size'))->load();

        $allResults = $allTweets->getTweets();

        // Render out tweets
        $this->setVar('tweet_block', '');

        if (count($allResults)) {
            $i = 0;
            foreach($allResults as $currentTweet) {
                $color = ++$i % 2 ? '' : 'dark';
                $date = new Alaress_Date_Format(strtotime($currentTweet['date']), 'U');

                $this->setVar(array(
                    'TWEET_TEXT' => Scrub::html($currentTweet['text']),
                    'TWEET_DATE' => $date->getShort(),
                    'TWEET_AUTHOR' => Scrub::text($currentTweet['author']),
                    'TWEET_FULLNAME' => Scrub::text($currentTweet['fullname']),
                    'TWEET_AVATAR' => Scrub::text($currentTweet['avatar']),
                    'ON_OFF' => $color,
                ));
                $this->parse('tweet_block', 'tweet', true);
            }
        }

		// Build Twitter Search URL
		$q = 'https://twitter.com/search?q=';
		$q .= urlencode($this->getConfig('username'));
		if($this->getConfig('username') && $this->getConfig('keywords')) {
			$q .= '%20';
		}
		$q .= urlencode($this->getConfig('keywords'));

		$this->setVar(array(
			'SEARCH_URL' => $q
		));

        return true;
    }

}
