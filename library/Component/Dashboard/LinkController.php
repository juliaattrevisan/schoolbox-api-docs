<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_LinkController extends Component_ControllerAbstract {

    protected $link;

    protected $linkList;

    public function __construct (Component_Instance $instance, User $user, Template $template, Link $link) {
        parent::__construct($instance, $user, $template);

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
            $this->linkList = array_merge($linkList, $tmp);
        }

        return true;
    }

    public function render ($templateHandle) {
        if ( count($this->linkList) > 0 ) {
            foreach ( $this->linkList as $link ) {
                $this->setVar(array(
                                   'ON_OFF' => ++$j % 2 ? '' : 'dark', 'LINK_TITLE' => scrubTEXT($link['description']),
                                   'LINK_URL' => scrubHTML($link['url'])
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