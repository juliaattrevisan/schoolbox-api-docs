<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_ManageFolderController extends Component_Homepage_ControllerAbstract
{

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var MyLinks
     */
    private $myLinkHandler;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $this->myLinkHandler = new MyLinks();
        $this->groupHandler  = new Group();
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'manageFolder')) {
            return false;
        }
//        $this->parseConfig();

        $this->setBlock($templateHandle, "manage_page", "manage_page_block");
        $this->setBlock($templateHandle, "stats_data", "stats_data_block");
        $this->setBlock($templateHandle, "addmylinks", "addmylinks_block");

        // My links button
        $url = 'http://' . Cfg::get('site_hostname') . '.' . Cfg::get('site_domain') . '/homepage/' . $this->getContext()->getId();

        if ($this->myLinkHandler->linkExists($_SESSION['id'], $url)) {
            $this->setVar('addmylinks_block', '');
        } else {
            $this->setVar('HOMEPAGE_URL', $url);
            $this->parse('addmylinks_block', 'addmylinks');
        }
        if ($this->permission >= FULL_ACCESS) {
            //is owner of area
            $this->parse("manage_page_block", "manage_page");
        } else {
            $this->setVar("manage_page_block", "");
        }

        if ($this->permission >= WRITE_ACCESS) {
            //has write access
            $this->parse("stats_data_block", "stats_data");
        } else {
            $this->setVar("stats_data_block", "");
        }

        // User subscription to groups.
        $this->setBlock($templateHandle, 'subscribed', 'subscribed_block');
        $this->setBlock($templateHandle, 'subscribedModerated', 'subscribedModerated_block');
        $this->setBlock($templateHandle, 'unsubscribed', 'unsubscribed_block');
        $this->setBlock($templateHandle, 'view_stats', 'view_stats_block');

        $this->setVar('subscribed_block', '');
        $this->setVar('subscribedModerated_block', '');
        $this->setVar('unsubscribed_block', '');

        // If you are subscribed but are awaiting authorisation
        $type = $this->groupHandler->involvedInGroup($_SESSION['id'], $this->getContext()->getId());

        if ($type == DENY_ACCESS && $type !== null) {
            $this->parse('subscribedModerated_block', 'subscribedModerated');
        } // If you are subscribed with something greater than DENY_ACCESS and you can subscribe, display the unsubscribe button
        elseif ($this->groupHandler->canSubscribe($this->getContext()->getId()) && ($type == READ_ACCESS || $type == WRITE_ACCESS)) {
            $this->parse('subscribed_block', 'subscribed');
        } elseif ($type == FULL_ACCESS) {
        } /* You aren't subscribed to the group yet.
        * If you can subscribe to the group, present option.
        * Otherwise do nothing
        */
        elseif ($this->groupHandler->canSubscribe($this->getContext()->getId())) {
            $this->parse('unsubscribed_block', 'unsubscribed');
        }

        /* Only write-access people can view statistics
		 */
        if ($this->permission >= WRITE_ACCESS) {
            $this->parse('view_stats_block', 'view_stats');
        } else {
            $this->setVar('view_stats_block', '');
        }

        return true; // everything went according to plan
    }
}
