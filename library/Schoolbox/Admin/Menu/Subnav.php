<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Menu;

use Schoolbox\IConfigLoader;


/**
 * Class Subnav
 *
 * A data structure which populates the little vertical sidemenu in admi
 *
 * @package Schoolbox\Admin\Menu
 */
class Subnav
{

    /**
     * The pre-generated menu structure
     * @var array
     */
    private array $nav = [];


    /**
     * For access to booleans that determine availability/visibility of certain menu
     * items based on plan, configuration, etc.
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
     */
    public function generate()
    {
        $stack = [
            'home' => [
                'name' => 'Home',
                'visible' =>  true,
                'children' => []
            ],
            'calendar' => [
                'name' => 'Calendar',
                'visible' =>  true,
                'children' => [
                    'cleanup' => [
                        'name' => 'Cleanup',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'import' => [
                        'name' => 'Import',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'subscribe' => [
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
                    'collaboration' => [
                        'name' => 'Collaboration',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'componentUsage' => [
                        'name' => 'Component Usage',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'dueWork' => [
                        'name' => 'Due Work',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'files' => [
                        'name' => 'Files',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'fileTypes' => [
                        'name' => 'File Types',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'links' => [
                        'name' => 'Links',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'login' => [
                        'name' => 'Login',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'mobileApps' => [
                        'name' => 'Mobile Apps',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'newsPosted' => [
                        'name' => 'News Posted',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'quizzes' => [
                        'name' => 'Quizzes',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'ePortfolio' => [
                'name' => 'ePortfolio',
                'visible' =>  true,
                'children' => []
            ],
            'filters' => [
                'name' => 'Filters',
                'visible' =>  true,
                'children' => []
            ],
            'homepageTemplates' => [
                'name' => 'Homepage Templates',
                'visible' =>  true,
                'children' => [
                    'cleanup' => [
                        'name' => 'Cleanup',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'import' => [
                        'name' => 'Import',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'subscribe' => [
                        'name' => 'Subscribe',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'import' => [
                'name' => 'Import',
                'visible' =>  true,
                'children' => []
            ],
            'lms' => [
                'name' => 'LMS',
                'visible' =>  true,
                'children' => []
            ],
            'lists' => [
                'name' => 'Lists',
                'visible' =>  true,
                'children' => [
                    'cleanup' => [
                        'badgeCategories' => 'Badge Categories',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'campus' => [
                        'name' => 'Campus',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'components' => [
                        'name' => 'Components',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'drives' => [
                        'name' => 'Drives',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'eventTypes' => [
                        'name' => 'Event Types',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'houses' => [
                        'name' => 'Houses',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'learningMoments' => [
                        'name' => 'Learning Moments',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'newsTopics' => [
                        'name' => 'News Topics',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'pastoralRecordSeverity' => [
                        'name' => 'Pastoral Record Severity',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'pastoralRecordTags' => [
                        'name' => 'Pastoral Record Tags',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'pastoralSisDocumentClassifications' => [
                        'name' => 'Pastoral SIS Document Classifications',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'pastoralSubTypes' => [
                        'name' => 'Pastoral Sub-Types',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'pastoralTypes' => [
                        'name' => 'Pastoral Types',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'residentialHouses' => [
                        'name' => 'Residential Houses',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'roles' => [
                        'name' => 'Roles',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'submissionStates' => [
                        'name' => 'Submission States',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'timetableColours' => [
                        'name' => 'Timetable Colours',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'userTitles' => [
                        'name' => 'User Titles',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'workTypes' => [
                        'name' => 'Work Types',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'yearLevels' => [
                        'name' => 'Year Levels',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'modules' => [
                'name' => 'Modules',
                'visible' =>  true,
                'children' => []
            ],
            'news' => [
                'name' => 'News',
                'visible' =>  true,
                'children' => []
            ],
            'notifications' => [
                'name' => 'Notifications',
                'visible' =>  true,
                'children' => []
            ],
            'personalisation' => [
                'name' => 'Personalisation',
                'visible' =>  true,
                'children' => []
            ],
            'resources' => [
                'name' => 'Resources',
                'visible' =>  true,
                'children' => []
            ],
            'schoolDataImport' => [
                'name' => 'School Data Import',
                'visible' =>  $this->configLoader->get('external_type') === 'schoolbox',
                'children' => []
            ],
            'systemSettings' => [
                'name' => 'System Settings',
                'visible' =>  true,
                'children' => [
                    '3rdPartyServices' => [
                        'name' => '3rd Party Services',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'boardLeave' => [
                        'name' => 'Board Leave',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'debugging' => [
                        'name' => 'Debugging',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'documentManagement' => [
                        'name' => 'Document Management',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'email' => [
                        'name' => 'Email',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'externalDb' => [
                        'name' => 'External DB',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'ldap' => [
                        'name' => 'LDAP',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lmsPolicy' => [
                        'name' => 'LMS Policy',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'localisation' => [
                        'name' => 'Localisation',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'modules' => [
                        'name' => 'Modules',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'pastoralCare' => [
                        'name' => 'Pastoral Care',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'paths' => [
                        'name' => 'Paths',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'policy' => [
                        'name' => 'Policy',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'proxy' => [
                        'name' => 'Proxy',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'resources' => [
                        'name' => 'Resources',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'saml' => [
                        'name' => 'SAML',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'security' => [
                        'name' => 'Security',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'site' => [
                        'name' => 'Site',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'skinConfig' => [
                        'name' => 'Skin Config',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'userManagement' => [
                'name' => 'User Management',
                'visible' =>  true,
                'children' => [
                    'users' => [
                        'name' => 'Users',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'bulkModify' => [
                        'name' => 'Bulk Modify',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'ups' => [
                        'name' => 'User Provisioning System',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'shaps' => [
                        'name' => 'Schoolbox Help Provisioning',
                        'visible' =>  true,
                        'children' => []
                    ],

                ]
            ],
            '' => [
                'name' => '',
                'visible' =>  true,
                'children' => []
            ],
        ];
    }
}