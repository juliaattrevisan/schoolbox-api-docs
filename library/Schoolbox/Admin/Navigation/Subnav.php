<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Navigation;

use Schoolbox\IConfigLoader;


/**
 * Class Subnav
 *
 * A data structure which populates the little vertical sidemenu in admin
 *
 * @package Schoolbox\Admin\Menu
 */
class Subnav
{


    /**
     * For access to booleans that determine availability/visibility of certain menu
     * items based on plan, configuration, etc.
     *
     * @var IConfigLoader
     */
    private IConfigLoader $configLoader;


    /**
     * Subnav constructor.
     *
     * @param IConfigLoader $configLoader
     */
    public function __construct(IConfigLoader $configLoader)
    {
        $this->configLoader = $configLoader;
    }


    /**
     * Generate the menu navigation
     * These are stored in (url, data) tuples where the key is the url fragment
     *
     * Some nodes are hybrids of other unrelated url sets, such as LMS. Its items
     * come from all over the place
     *
     * @param string|null $pageCurrent
     * @return array
     */
    public function generate(?string $pageCurrent): array
    {
        return [
            'home' => [
                'name' => 'Home',
                'visible' =>  true,
                'children' => []
            ],
            'calendar' => [
                'name' => 'Calendar',
                'visible' =>  true,
                'children' => [
                    'calendar/Cleanup' => [
                        'name' => 'Cleanup',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'calendar/Import' => [
                        'name' => 'Import',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'calendar/Subscribe' => [
                        'name' => 'Subscribe',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'engagement' => [
                'name' => 'Engagement',
                'visible' =>  true,
                'children' => [
                    'engagement/index/collaboration' => [
                        'name' => 'Collaboration',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/components' => [
                        'name' => 'Component Usage',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/dueWork' => [
                        'name' => 'Due Work',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/files' => [
                        'name' => 'Files',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/fileTypes' => [
                        'name' => 'File Types',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/links' => [
                        'name' => 'Links',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/login' => [
                        'name' => 'Login',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/mobile' => [
                        'name' => 'Mobile Apps',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/news' => [
                        'name' => 'News Posted',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/quiz' => [
                        'name' => 'Quizzes',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'eportfolio' => [
                'name' => 'ePortfolio',
                'visible' =>  true,
                'children' => [
                    'ePortfolio' => [
                        'name' => 'Templates',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'ePortfolio/deploy' => [
                        'name' => 'Deployment',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'filters' => [
                'name' => 'Filters',
                'visible' =>  true,
                'children' => []
            ],
            'homepage' => [
                'name' => 'Homepage Templates',
                'visible' =>  true,
                'children' => []
            ],
            'import/directImport' => [
                'name' => 'Import',
                'visible' =>  true,
                'children' => [
                    'import/folder' => [
                        'name' => 'Pages',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'import/pastoral' => [
                        'name' => 'Pastoral Care',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'import/photos/upload' => [
                        'name' => 'Portrait Photos',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'import/userAccount' => [
                        'name' => 'User Accounts',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'lms' => [
                'name' => 'LMS',
                'visible' =>  true,
                'children' => [
                    'terms' => [
                        'name' => 'Academic Period',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'rollover' => [
                        'name' => 'Class Rollover',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'course-builder' => [
                        'name' => 'Course Builder',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Document+Management' => [
                        'name' => 'Document Management',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'grades' => [
                        'name' => 'Grades',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/LMS+Policy' => [
                        'name' => 'LMS Policy',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lti' => [
                        'name' => 'LTI Providers',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'rubric-results' => [
                        'name' => 'Rubrics',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'lists' => [
                'name' => 'Lists',
                'visible' =>  true,
                'children' => [
                    'lists/achievement-category' => [
                        'name' => 'Badge Categories',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/campus' => [
                        'name' => 'Campus',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/component-definition' => [
                        'name' => 'Components',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/webdav' => [
                        'name' => 'Drives',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/event-type' => [
                        'name' => 'Event Types',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/house' => [
                        'name' => 'Houses',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/evidence-tag' => [
                        'name' => 'Learning Moments',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/news-topic' => [
                        'name' => 'News Topics',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/pastoral-severity' => [
                        'name' => 'Pastoral Record Severity',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/pastoral-tags' => [
                        'name' => 'Pastoral Record Tags',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/pastoral-document-classification' => [
                        'name' => 'Pastoral SIS Document Classifications',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/pastoral-category' => [
                        'name' => 'Pastoral Sub-Types',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/pastoral-type' => [
                        'name' => 'Pastoral Types',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/residential-house' => [
                        'name' => 'Residential Houses',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/roles' => [
                        'name' => 'Roles',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/submission-errors' => [
                        'name' => 'Submission States',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/timetable-colour' => [
                        'name' => 'Timetable Colours',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/user-title' => [
                        'name' => 'User Titles',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/submission-type' => [
                        'name' => 'Work Types',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/year-levels' => [
                        'name' => 'Year Levels',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'setting/Modules' => [
                'name' => 'Modules',
                'visible' =>  true,
                'children' => [
                    'setting/Board+Leave' => [
                        'name' => 'Board Leave',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'feature-tours' => [
                        'name' => 'Feature Tours',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'package' => [
                        'name' => 'Packages',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Pastoral+Care' => [
                        'name' => 'Pastoral Care',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'news' => [
                'name' => 'News',
                'visible' =>  true,
                'children' => []
            ],
            'messages' => [
                'name' => 'Notifications',
                'visible' =>  true,
                'children' => [
                    'messages/apps' => [
                        'name' => 'Delivery Providers',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'messages/puush' => [
                        'name' => 'Mobile App Registrations',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'messages/configure' => [
                        'name' => 'Settings',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'personalisation' => [
                'name' => 'Personalisation',
                'visible' =>  true,
                'children' => [
                    'dashboard' => [
                        'name' => 'Dashboard',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'profile-button' => [
                        'name' => 'Profile Buttons',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'menu-side' => [
                        'name' => 'Side Menu',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Skin+Config' => [
                        'name' => 'Skin Config',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'terminology' => [
                        'name' => 'Terminology',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'menu-top' => [
                        'name' => 'Top Menu',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'setting/Resources' => [
                'name' => 'Resources',
                'visible' =>  true,
                'children' => [
                    'allowed-file' => [
                        'name' => 'Allowed File',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'video' => [
                        'name' => 'Media Queue',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Resources' => [
                        'name' => 'Settings',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'restore' => [
                        'name' => 'Restore',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'usage' => [
                        'name' => 'Usage',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'import/schoolDataImport' => [
                'name' => 'School Data Import',
                'visible' =>  $this->configLoader->get('external_type') === 'schoolbox',
                'children' => []
            ],
            'setting' => [
                'name' => 'System Settings',
                'visible' =>  true,
                'children' => [
                    'setting/3rd+Party+Services' => [
                        'name' => '3rd Party Services',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Debugging' => [
                        'name' => 'Debugging',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Email' => [
                        'name' => 'Email',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/External+DB' => [
                        'name' => 'External DB',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/LDAP' => [
                        'name' => 'LDAP',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Localisation' => [
                        'name' => 'Localisation',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'oauth' => [
                        'name' => 'oAuth',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Paths' => [
                        'name' => 'Paths',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Policy' => [
                        'name' => 'Policy',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Proxy' => [
                        'name' => 'Proxy',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/SAML' => [
                        'name' => 'SAML',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Security' => [
                        'name' => 'Security',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Site' => [
                        'name' => 'Site',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'user' => [
                'name' => 'User Management',
                'visible' =>  true,
                'children' => [
                    'reports/activity/all' => [
                        'name' => 'Activity Report',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'user/bulk/modify' => [
                        'name' => 'Bulk Modify',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'reports/login/all' => [
                        'name' => 'Login Log',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'shaps' => [
                        'name' => 'Schoolbox Help Provisioning',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'ups' => [
                        'name' => 'User Provisioning System',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
        ];
    }
}