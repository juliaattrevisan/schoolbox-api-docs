<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_RSSController extends Component_Homepage_ControllerAbstract {

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
        if(!parent::loadTemplate($templateHandle, 'rss')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'rss_stories', 'rss_block');
        $this->setBlock('rss_stories', 'rss_date', 'rss_date_block');

        // Initialise feed
        $rss = new Alaress_Rss_Stream($this->getConfig('url'));
        $rss->setLimit($this->getConfig('size'));
        $rss->stream();

        // Set up feed info
        $this->setVar(array(
            'RSS_NAME' => Scrub::text($rss->getTitle()),
            'RSS_LINK' => $rss->getLink(),
        ));

        $this->setVar('rss_block', '');

        // Process each item in feed
        $allRss = $rss->getOutput();

        if (count($allRss)) {
            $j = 0;

            foreach($allRss as $currentRss) {

                $this->setVar(array(
                    'ON_OFF'    => ++$j % 2 ? '' : 'dark',
                    'RSS_TITLE' => Scrub::text($currentRss['title']),
                    'RSS_URL'   => urlencode(base64_encode($currentRss['link'])),
                    'RSS_BRIEF' => Scrub::html( $currentRss['content'] ? $currentRss['content'] : $currentRss['description']),
                ));

                // If the item has a pubDate
                if (strlen($currentRss['pubDate'])) {
                    $postedDate = new Alaress_Date_Relative(strtotime($currentRss['pubDate']), 'U');

                    $this->setVar('RSS_POSTED_DATE', $postedDate->getRelativeTime());
                    $this->parse('rss_date_block', 'rss_date');
                }
                else {
                    $this->setVar('rss_date_block', '');
                }

                // Parse the rss item row
                $this->parse('rss_block', 'rss_stories', true);
            }
        }
        else {
            $this->setVar('rss_block', '');
        }

        return true; // everything went according to plan
    }
}
