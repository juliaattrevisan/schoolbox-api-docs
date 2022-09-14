<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 */

use Schoolbox\Application\Exception\Exception as SchoolboxException;

class Component_Dashboard_RssController extends Component_Dashboard_ControllerAbstract
{
    protected $rss;

    public function __construct(Component_Instance $instance, \Schoolbox\User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);


        $this->rss = new Alaress_Rss_Stream($this->getConfig('url'));
        $size = $this->getConfig('size');
        if (!$size) {
            $size = Config::get('welcome_rss_limit');
        }
        $this->rss->setLimit($size);
        try {
            $this->rss->stream();
        } catch (SchoolboxException $e) {
            /**
             * `$this->rss->stream()` has the ability to throw a `SchoolboxException` usually due to a Guzzle exception
             * will only occur when the cache gets the URL the first time, or on a cache refresh.
             * catch this and set an empty state.
             */
        }
    }


    public function renderTemplate($templateHandle)
    {
        if (!$this->loadTemplate($templateHandle, 'rss')) {
            return false;
        }

        $this->setBlock($templateHandle, "rss_stories", "rss_block");
        $this->setBlock('rss_stories', 'rss_date', 'rss_date_block');
        $this->setBlock('rss_stories', 'rss_brief_output', 'rss_brief_output_block');
        $this->setVar('rss_block', '');

        $this->setComponentName($this->rss->getTitle());
        $this->setComponentHref($this->rss->getLink());

        $allRss = $this->rss->getOutput();
        $compactMode = $this->getConfig('compactMode');
        $j = 0;
        if (count($allRss)) {
            foreach ($allRss as $item) {
                $this->setVar(
                    array(
                        'ON_OFF' => ++$j % 2 ? '' : 'dark',
                        'RSS_TITLE' => Scrub::text($item['title']),
                        'RSS_URL' => urlencode(base64_encode($item['link'])),
                        'RSS_BRIEF' => Scrub::html($item['content'] ?? $item['description']),
                        'rss_brief_output_block' => '',
                    )
                );
                if (!$compactMode) {
                    $this->parse('rss_brief_output_block', 'rss_brief_output');
                }
                if ($item['pubDate'] instanceof DateTime) {
                    $postedDate = new Alaress_Date_Format($item['pubDate']);
                    $this->setVar('RSS_POSTED_DATE', $postedDate->getRelativeTime());
                    $this->parse('rss_date_block', 'rss_date');
                } else {
                    $this->setVar('rss_date_block', '');
                }

                $this->parse("rss_block", "rss_stories", true);
            }
            $this->parseConfig();
            return true;
        } else {
            return false;
        }
    }
}
