<?php

use Schoolbox\IConfigLoader;

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class AdminV2_Nav
{

    /**
     * @var array
     */
    private $nav;


    /**
     * AdminV2_Nav constructor.
     *
     * @param IConfigLoader $configLoader
     */
    public function __construct(IConfigLoader $configLoader)
    {
        $this->nav = [
            'home'               => ['name' => 'Home', 'visible' => true],
            'user'               => ['name' => 'Users', 'visible' => true],
            'menu-side'          => ['name' => 'Side Menu', 'visible' => true],
            'menu-top'           => ['name' => 'Top Menu', 'visible' => true],
            'profile-button'     => ['name' => 'Profile Buttons', 'visible' => true],
            'dashboard'          => ['name' => 'Dashboard', 'visible' => true],
            'homepage'           => ['name' => 'Homepages', 'visible' => true],
            'eportfolio'         => ['name' => 'ePortfolios', 'visible' => true],
            'eportfolio/deploy'  => ['name' => 'ePortfolio Deployment', 'visible' => true],
            'news'               => ['name' => 'News', 'visible' => true],
            'allowed-file'       => ['name' => 'Allowed Files', 'visible' => true],
            'setting'            => ['name' => 'Settings', 'visible' => true],
            'terminology'        => ['name' => 'Terminology', 'visible' => true],
            'calendar'           => ['name' => 'Calendar', 'visible' => true],
            'rollover'           => ['name' => 'Rollover', 'visible' => true],
            'grades'             => ['name' => 'Grades', 'visible' => true],
            'rubric-results'     => ['name' => 'Rubrics', 'visible' => true],
            'terms'              => ['name' => 'Terms', 'visible' => true],
            'course-builder'     => ['name' => 'Course Builder', 'visible' => true],
            'messages'           => ['name' => 'Notifications', 'visible' => true],
            'lists'              => ['name' => 'Lists', 'visible' => true],
            'lti'                => ['name' => 'LTI Providers', 'visible' => true],
            'usage'              => ['name' => 'Usage', 'visible' => true],
            'engagement'         => ['name' => 'Engagement', 'visible' => true],
            'ups'                => ['name' => 'User Provisioning System', 'visible' => true],
            'shaps/provisioning' => ['name' => 'Schoolbox Help Provisioning', 'visible' => true],
            'video'              => ['name' => 'Media Queue', 'visible' => true],

             /* If it's schoolbox, add the (import/schoolData => 'School Data Import') tuple
              * (it only displays if are using the schoolbox connector)
              */
            'import/schoolData'  => ['name' => 'School Data Import', 'visible' => $configLoader->get('external_type') === 'schoolbox'],

            'import/direct'      => ['name' => 'Direct Import', 'visible' => true],
            'filters'            => ['name' => 'Filters', 'visible' => true],
            'feature-tours'      => ['name' => 'Feature Tours', 'visible' => true],
        ];
    }


    /**
     * phpliberty island
     *
     * @param SitePage $page
     * @param string   $currentPage
     */
    public function parse(SitePage $page, $currentPage = 'home'): void
    {
        $page->setFile('nav', 'adminv2/nav.html');
        $page->setBlock('nav', 'admin_nav_item', 'admin_nav_item_block');

        foreach ($this->nav as $href => $node) {
            if (!$node['visible']) {
                continue;
            }

            $label = $node['name'];

            $page->setVar([
                'ADMNAV_HREF'    => $href,
                'ADMNAV_CURRENT' => $currentPage === $href ? 'active' : '',
                'ADMNAV_LABEL'   => $label
            ]);

            $page->parse('admin_nav_item_block', 'admin_nav_item', true);
        }

        $page->setVar([
            'CURRENT_ADMIN_LOCATION' => $this->nav[$currentPage]['name']
        ]);

        $page->parse('ADMIN_NAV', 'nav');
    }


    /**
     * @return array
     */
    public function getNavigation(): array
    {
        return $this->nav;
    }
}
