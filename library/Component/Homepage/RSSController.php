<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

class Component_Homepage_RSSController extends Component_Homepage_ControllerAbstract
{
    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'rss')) {
            return false;
        }

        $this->setBlock($templateHandle, 'has_feed', 'has_feed_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        $emptyState = false;
        $noConfig   = false;

        if (strlen($this->getConfig('url')) == 0) {
            $emptyState = true;
            $noConfig   = true;
        } else {
            // Initialise feed
            $rss = new Alaress_Rss_Stream($this->getConfig('url'));
            $rss->setLimit($this->getConfig('size'));

            try {
                $rss->stream();
                // Process each item in feed
                $allRss = $rss->getOutput();
            } catch (SchoolboxException $e) {
                /**
                 * `$rss->stream()` has the ability to throw a `SchoolboxException` usually due to a Guzzle exception
                 * will only occur when the cache gets the URL the first time, or on a cache refresh.
                 * catch this and set an empty state.
                 */
                $allRss = [];
            }
            if (count($allRss) == 0) {
                $emptyState = true;
            }
        }

        if ($emptyState) {
            $this->setBlock('empty_state', 'empty_config', 'empty_config_block');
            $this->setBlock('empty_state', 'empty_invalid', 'empty_invalid_block');

            $this->setVar(
                [
                    'has_feed_block'      => '',
                    'empty_config_block'  => '',
                    'empty_invalid_block' => '',
                ]
            );

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                return false;
            } elseif ($noConfig) {
                $this->parse('empty_config_block', 'empty_config');
            } else {
                $this->parse('empty_invalid_block', 'empty_invalid');
            }

            $this->parse('empty_state_block', 'empty_state');
        } else {
            $this->setBlock('has_feed', 'rss_stories', 'rss_stories_block');
            $this->setBlock('rss_stories', 'rss_date', 'rss_date_block');
            $this->setBlock('rss_stories', 'rss_brief_output', 'rss_brief_output_block');

            //Clear data from previous RSS feed
            $this->setVar('rss_stories_block', '');

            $title = Scrub::text($rss->getTitle());

            if (strlen($title)) {
                $this->setComponentName($title);
            }

            // Set up feed info
            $this->setVar(
                [
                    'empty_state_block' => '',
                    'RSS_LINK'          => $rss->getLink(),
                    'rss_block'         => '',
                ]
            );

            $compactMode = $this->getConfig('compactMode');

            $j = 0;

            foreach ($allRss as $currentRss) {
                $this->setVar(
                    [
                        'RSS_TITLE'              => Scrub::text($currentRss['title']),
                        'RSS_URL'                => urlencode(base64_encode($currentRss['link'])),
                        'RSS_BRIEF'              => Scrub::html($currentRss['content'] ? $currentRss['content'] : $currentRss['description']),
                        'rss_brief_output_block' => '',
                    ]
                );

                if (!$compactMode) {
                    $this->parse('rss_brief_output_block', 'rss_brief_output');
                }

                // If the item has a pubDate
                if ($currentRss['pubDate'] instanceof DateTime) {
                    $postedDate = new Alaress_Date_Format($currentRss['pubDate']);

                    $this->setVar('RSS_POSTED_DATE', $postedDate->getRelativeTime());
                    $this->parse('rss_date_block', 'rss_date');
                } else {
                    $this->setVar('rss_date_block', '');
                }

                // Parse the rss item row
                $this->parse('rss_stories_block', 'rss_stories', true);
            }

            $this->parse('has_feed_block', 'has_feed');
        }

        /* Need rss name injected here
         */
        $this->parseConfig();

        return true; // everything went according to plan
    }
}
