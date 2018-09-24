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
        '/adminv2/home'               => 'Home',
        '/adminv2/user'               => 'Users',
        '/adminv2/menu-side'          => 'Side Menu',
        '/adminv2/menu-top'           => 'Top Menu',
        '/adminv2/dashboard'          => 'Dashboard',
        '/adminv2/homepage'           => 'Homepages',
        '/adminv2/eportfolio'         => 'ePortfolios',
        '/adminv2/eportfolio/deploy'  => 'ePortfolio Deployment',
        '/comms/news/policy'          => 'News',
        '/adminv2/allowed-file'       => 'Allowed Files',
        '/adminv2/setting'            => 'Settings',
        '/adminv2/terminology'        => 'Terminology',
        '/adminv2/calendar'           => 'Calendar',
        '/adminv2/bulk-add-folders'   => 'Folder',
        '/adminv2/rollover'           => 'Rollover',
        '/adminv2/grades'             => 'Grades',
        '/adminv2/rubric-results'     => 'Rubrics',
        '/adminv2/terms'              => 'Terms',
        '/adminv2/course-builder'     => 'Course Builder',
        '/adminv2/messages'           => 'Notifications',
        '/adminv2/lists'              => 'Lists',
        '/adminv2/lti'                => 'LTI Providers',
        '/adminv2/cleanup'            => 'Cleanup',
        '/adminv2/usage'              => 'Usage',
        '/adminv2/engagement'         => 'Engagement',
        '/adminv2/ups'                => 'User Provisioning System',
        '/adminv2/shaps/provisioning' => 'Schoolbox Help Provisioning',
        '/adminv2/video'              => 'Media Queue',
        '/adminv2/import'             => 'Import',
        '/adminv2/filters'            => 'Filters',
        '/adminv2/feature-tours'      => 'Feature Tours',
    );

    public function parse(SitePage $page, $currentPage = 'home')
    {
        $page->setFile('nav', 'adminv2/nav.html');
        $page->setBlock('nav', 'admin_nav_item', 'admin_nav_item_block');

        foreach ($this->nav as $href => $label) {
            $page->setVar(array(
                'ADMNAV_HREF'    => $href,
                'ADMNAV_CURRENT' => $currentPage == $href ? 'active' : '',
                'ADMNAV_LABEL'   => $label
            ));
            $page->parse('admin_nav_item_block', 'admin_nav_item', true);
        }

        $page->setVar(array(
            'CURRENT_ADMIN_LOCATION' => $this->nav[$currentPage]
        ));

        $page->parse('ADMIN_NAV', 'nav');
    }

    public function getNavigation() {
        return $this->nav;
    }
}
