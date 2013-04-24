<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class AdminV2_Nav {

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
        'rubric-results' => 'Rubrics',
        'list' => 'Lists',
        'cleanup' => 'Cleanup'
    );

    public function parse(SitePage $page, $currentPage = 'home') {
        $page->setBlock(TEMPLATE_content, 'admin_nav_item', 'admin_nav_item_block');
        foreach($this->nav as $href => $label) {
            $page->setVar(array(
                'ADMNAV_HREF' => $href,
                'ADMNAV_CURRENT' => $currentPage == $href ? 'current' : '',
                'ADMNAV_LABEL' => $label
            ));
            $page->parse('admin_nav_item_block', 'admin_nav_item', true);
        }
    }
}
