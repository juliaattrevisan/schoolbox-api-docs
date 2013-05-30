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
	    $this->rss->setLimit(Cfg::get('welcome_rss_limit'));
	    $this->rss->stream();

	    //$this->rss = new Xml($this->getConfig('url'), Cfg::get('xml_cache_timeout'));
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

        $this->setVar('RSS_NAME', $this->rss->channel->title);
        $this->setVar('RSS_LINK', base64_encode($this->rss->channel->link));

	    $allRss = $this->rss->getOutput();

	    if (count($allRss)) {
		    foreach($allRss as $item) {
	            //$content = $item->children('http://purl.org/rss/1.0/modules/content/');
                $this->setVar(
                    array(
                        'ON_OFF' => ++$j % 2 ? '' : 'dark',
                        'RSS_TITLE' => Scrub::text($item['title']),
                        'RSS_URL' => base64_encode($item['link']),
                        'RSS_BRIEF' => Scrub::html($item['content']),
                    )
                );
                if (strlen($item->pubDate)) {
                    $postedDate = new Alaress_Date_Relative(new DateTime($item->pubDate), 'U');
                    $this->setVar('RSS_POSTED_DATE', $postedDate->getRelativeTime());
                    $this->parse('rss_date_block', 'rss_date');
                }
                else {
                    $this->setVar('rss_date_block', '');
                }

                $this->parse("rss_block", "rss_stories", true);

                if ($j == Cfg::get('welcome_rss_limit')) {
                    break;
                }
            }

            return true;
        } else {
            return false;
        }
    }

}
