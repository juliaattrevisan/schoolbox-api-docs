<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

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
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if (!$this->isSourceRenderable() && !$this->hasContextWriteAccess()) {
            return false;
        }

        $this->myLinkHandler = new MyLinks();
        $this->groupHandler  = new Group();
        return true;
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

        $this->setBlock($templateHandle, "manage_page", "manage_page_block");
        $this->setBlock($templateHandle, "stats_data", "stats_data_block");
        $this->setBlock($templateHandle, "addmylinks", "addmylinks_block");
        $this->setBlock($templateHandle, "manage_textboxes", "manage_textboxes_block");

        // My links button
        $url = 'https://' . Config::get('site_hostname') . '.' . Config::get('site_domain') . '/homepage/' . $this->getContext()->getId();

        if ($this->myLinkHandler->linkExists($_SESSION['id'], $url)) {
            $this->setVar('addmylinks_block', '');
        } else {
            $this->setVar('HOMEPAGE_URL', $url);
            $this->setVar('HOMEPAGE_URL_JS', json_encode($url));
            $this->parse('addmylinks_block', 'addmylinks');
        }
        if ($this->getContentPermission() >= UserAccess::ACCESS_FULL) {
            //is owner of area
            if ($this->getContext()->getInherit()) {
                $this->setVar("manage_page_block", "");
            } else {
                $this->parse("manage_page_block", "manage_page");
            }
        } else {
            $this->setVar("manage_page_block", "");
        }

        if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
            //has write access
            $this->parse("stats_data_block", "stats_data");
            $this->setVar('VIEW_FILES_LINK_NAME', 'Manage Files');

            /* Allow Manage Textboxes
             */
            $this->parse('manage_textboxes_block', 'manage_textboxes');
        } else {
            $this->setVar([
                'stats_data_block' => '',
                'manage_textboxes_block' => ''
            ]);
            $this->setVar('VIEW_FILES_LINK_NAME', 'View Files');
        }
        $this->setVar('VIEW_FILES_LINK', $this->getRouter()->generate('resourceBrowser.showFolderFiles', ['sourceFolderID' => $this->getContext()->getId()]));

        // User subscription to groups.
        $this->setBlock($templateHandle, 'subscribed', 'subscribed_block');
        $this->setBlock($templateHandle, 'subscribedModerated', 'subscribedModerated_block');
        $this->setBlock($templateHandle, 'unsubscribed', 'unsubscribed_block');
        $this->setBlock($templateHandle, 'view_stats', 'view_stats_block');

        $this->setVar('subscribed_block', '');
        $this->setVar('subscribedModerated_block', '');
        $this->setVar('unsubscribed_block', '');

        $type = $this->groupHandler->involvedInGroup($_SESSION['id'], $this->getContext()->getId());

        if ($type == UserAccess::ACCESS_DENY && $type !== null && $type !== false) {
            // If you are subscribed but are awaiting authorisation
            $this->parse('subscribedModerated_block', 'subscribedModerated');
        } elseif ($this->groupHandler->canSubscribe($this->getContext()->getId()) && ($type == UserAccess::ACCESS_READ || $type == UserAccess::ACCESS_WRITE)) {
            // If you are subscribed with something greater than ACCESS_DENY and you can subscribe, display the unsubscribe button
            $this->parse('subscribed_block', 'subscribed');
        } elseif ($type == UserAccess::ACCESS_FULL) {
            // don't show anything
        } elseif ($this->groupHandler->canSubscribe($this->getContext()->getId())) {
            /* You aren't subscribed to the group yet.
             * If you can subscribe to the group, present option.
             * Otherwise do nothing
             */
            $this->parse('unsubscribed_block', 'unsubscribed');
        }

        /* Only write-access people can view statistics
         */
        if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
            $this->parse('view_stats_block', 'view_stats');
        } else {
            $this->setVar('view_stats_block', '');
        }

        return true; // everything went according to plan
    }
}
