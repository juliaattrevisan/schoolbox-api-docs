<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_RssController extends Component_Dashboard_ControllerAbstract {

    protected $rss;

    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage) {
        parent::__construct($instance, $user, $sitepage);


        $this->rss = new Alaress_Rss_Stream($this->getConfig('url'));
        $size = $this->getConfig('size');
        if (!$size) {
            $size = Cfg::get('welcome_rss_limit');
        }
        $this->rss->setLimit($size);
        $this->rss->stream();

    }

    public function execute() {

        return true;
    }

    public function renderTemplate($templateHandle) {
        if (!$this->loadTemplate($templateHandle, 'rss')) {
            return false;
        }

        $this->setBlock($templateHandle, "rss_stories", "rss_block");
        $this->setBlock('rss_stories', 'rss_date', 'rss_date_block');

        $this->setVar('RSS_NAME', Scrub::text($this->rss->getTitle()));
        $this->setVar('RSS_LINK', urlencode(base64_encode($this->rss->getLink())));

        $allRss = $this->rss->getOutput();

        $j = 0;
        if (count($allRss)) {
            foreach($allRss as $item) {

                $this->setVar(
                    array(
                        'ON_OFF' => ++$j % 2 ? '' : 'dark',
                        'RSS_TITLE' => Scrub::text($item['title']),
                        'RSS_URL' => urlencode(base64_encode($item['link'])),
                        'RSS_BRIEF' => Scrub::html($item['content']?$item['content']:$item['description']),
                    )
                );
                if (strlen($item['pubDate'])) {
                    $postedDate = new Alaress_Date_Relative(new DateTime($item['pubDate']), 'U');
                    $this->setVar('RSS_POSTED_DATE', $postedDate->getRelativeTime());
                    $this->parse('rss_date_block', 'rss_date');
                }
                else {
                    $this->setVar('rss_date_block', '');
                }

                $this->parse("rss_block", "rss_stories", true);
            }

            return true;
        } else {
            return false;
        }
    }

}
