<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_RssController extends Component_ControllerAbstract {

    protected $rss;

    public function __construct (Component_Instance $instance, User $user, Template $template) {
        parent::__construct($instance, $user, $template);

        $this->rss = new Xml(Cfg::get('welcome_rss_url'), RSS_cache_time);
    }

    public function execute () {

        return true;
    }

    public function render ($templateHandle) {
        $this->setBlock($templateHandle, "rss_stories", "rss_block");

        $this->setVar('RSS_NAME', $this->rss->channel->title);
        $this->setVar('RSS_LINK', base64_encode($this->rss->channel->link));

        if ( $this->rss->channel ) {
            foreach ( $this->rss->channel->item as $i => $item ) {
                $this->setVar(array(
                                   'ON_OFF' => ++$j % 2 ? '' : 'dark', 'RSS_TITLE' => Scrub::text($item->title),
                                   'RSS_URL' => scrubHTML($item->link)
                              ));

                $this->parse("rss_block", "rss_stories", true);

                if ( $j == Cfg::get('welcome_rss_limit') ) {
                    break;
                }
            }

            return true;
        }
        else {
            return false;
        }
    }

}