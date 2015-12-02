<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class AdminV2_Nav
{

    private $nav = array(
        'home' => 'Home',
        'user' => 'Users',
        'side-menu' => 'Side Menu',
        'dashboard' => 'Dashboard',
        'homepage' => 'Homepages',
        'eportfolio' => 'ePortfolios',
        'allowed-file' => 'Allowed Files',
        'setting' => 'Settings',
        'calendar' => 'Calendar',
        'folder' => 'Folder',
        'rollover' => 'Rollover',
        'grades' => 'Grades',
        'rubric-results' => 'Rubrics',
        'messages' => 'Messages',
        'list' => 'Lists',
        'cleanup' => 'Cleanup',
        'usage' => 'Usage',
        'engagement' => 'Engagement',
        'synchronise' => 'Synchronise',
    );

    public function parse(SitePage $page, $currentPage = 'home')
    {
        $page->setFile('nav', 'adminv2/nav.html');
        $page->setBlock('nav', 'admin_nav_item', 'admin_nav_item_block');

        foreach ($this->nav as $href => $label) {
            $page->setVar(array(
                'ADMNAV_HREF' => $href,
                'ADMNAV_CURRENT' => $currentPage == $href ? 'active' : '',
                'ADMNAV_LABEL' => $label
            ));
            $page->parse('admin_nav_item_block', 'admin_nav_item', true);
        }

        $page->setVar(array(
            'CURRENT_ADMIN_LOCATION' => $this->nav[$currentPage]
        ));

        $page->parse('ADMIN_NAV', 'nav');
    }
}
