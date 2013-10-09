<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_LinkController extends Component_Dashboard_ControllerAbstract {

    protected $link;

    protected $linkList;

    public function __construct (Component_Instance $instance, \Schoolbox\User $user, SitePage $sitepage, Link $link) {
        parent::__construct($instance, $user, $sitepage);

        $this->link = $link;
    }

    public function execute () {
        foreach ( $_SESSION['campus'] as $campus ) {

            if ( $campus == 1 ) //Senior
            {
                $cidList[] = 6022;
            }

            if ( $campus == 2 ) //Junior
            {
                $cidList[] = 6023;
            }

            if ( $campus == 3 ) //Middle
            {
                $cidList[] = 6024;
            }
        }

        $linkList = array();

        foreach ( $cidList as $cid ) {
            $tmp = $this->link->getAllInCategory($cid);
            $this->linkList = array_merge($linkList, array($tmp));
        }

        return true;
    }

    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'link') ) {
            return false;
        }

        $this->setBlock($templateHandle, 'quicklink', 'quicklink_block');

        if ( count($this->linkList) > 0 ) {
            foreach ( $this->linkList as $link ) {
                $this->setVar(array(
                                   'ON_OFF' => ++$j % 2 ? '' : 'dark', 'LINK_TITLE' => Scrub::text($link['description']),
                                   'LINK_URL' => Scrub::html($link['url'])
                              ));

                $this->parse("quicklink_block", "quicklink", true);
            }

            return true;
        }
        else {
            return false;
        }

        return true;
    }

}
