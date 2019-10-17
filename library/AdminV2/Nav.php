<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class AdminV2_Nav
{
    private $nav = array(
        'home'               => 'Home',
        'user'               => 'Users',
        'menu-side'          => 'Side Menu',
        'menu-top'           => 'Top Menu',
        'profile-button'     => 'Profile Buttons',
        'dashboard'          => 'Dashboard',
        'homepage'           => 'Homepages',
        'eportfolio'         => 'ePortfolios',
        'eportfolio/deploy'  => 'ePortfolio Deployment',
        'news'               => 'News',
        'allowed-file'       => 'Allowed Files',
        'setting'            => 'Settings',
        'terminology'        => 'Terminology',
        'calendar'           => 'Calendar',
        'rollover'           => 'Rollover',
        'grades'             => 'Grades',
        'rubric-results'     => 'Rubrics',
        'terms'              => 'Terms',
        'course-builder'     => 'Course Builder',
        'messages'           => 'Notifications',
        'lists'              => 'Lists',
        'lti'                => 'LTI Providers',
        'usage'              => 'Usage',
        'engagement'         => 'Engagement',
        'ups'                => 'User Provisioning System',
        'shaps/provisioning' => 'Schoolbox Help Provisioning',
        'video'              => 'Media Queue',
        'import/schoolData'  => 'School Data Import',
        'import/direct'      => 'Direct Import',
        'filters'            => 'Filters',
        'feature-tours'      => 'Feature Tours',
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
