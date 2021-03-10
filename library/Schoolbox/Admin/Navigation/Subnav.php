<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Navigation;

use Schoolbox\IConfigLoader;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;


/**
 * Class Subnav
 *
 * A data structure which populates the little vertical sidemenu in admin
 *
 * @package Schoolbox\Admin\Menu
 */
class Subnav
{

    /* Arbitrary strings that uniquely identify a page and its subpages
     * Used to preselect menu options so users have a sense of where they are
     * The strings can actually be anything, there's no particular format
     *
     * Although, sometimes controllers get these values from a url parameter
     * and won't be explicitly stated as one of these consts, so be mindful
     * that there are multiple ways to match these identifiers
     */
    public const PARENT_HOME = 'home';

    public const PARENT_CALENDAR = 'calendar';
    public const CHILD_CALENDAR_CLEANUP = 'Cleanup';
    public const CHILD_CALENDAR_IMPORT = 'Import';
    public const CHILD_CALENDAR_SUBSCRIBE = 'Subscribe';

    public const PARENT_ENGAGEMENT = 'engagement';
    public const CHILD_ENGAGEMENT_COLLABORATION = 'collaboration';
    public const CHILD_ENGAGEMENT_COMPONENT_USAGE = 'components';
    public const CHILD_ENGAGEMENT_DUE_WORK = 'dueWork';
    public const CHILD_ENGAGEMENT_FILES = 'files';
    public const CHILD_ENGAGEMENT_FILETYPES = 'fileTypes';
    public const CHILD_ENGAGEMENT_LINKS = 'links';
    public const CHILD_ENGAGEMENT_LOGIN = 'login';
    public const CHILD_ENGAGEMENT_MOBILE_APPS = 'mobile';
    public const CHILD_ENGAGEMENT_NEWS_POSTED = 'news';
    public const CHILD_ENGAGEMENT_QUIZZES = 'quiz';

    public const PARENT_EPORTFOLIO = 'eportfolio';
    public const CHILD_EPORTFOLIO_TEMPLATES = 'templates';
    public const CHILD_EPORTFOLIO_DEPLOYMENT = 'deployment';

    public const PARENT_FILTERS = 'filters';
    public const PARENT_HOMEPAGE = 'homepage';
    public const PARENT_IMPORT = 'import/directImport';
    public const PARENT_LISTS = 'lists';
    public const PARENT_LMS = 'lms';
    public const PARENT_MESSAGES = 'messages';
    public const PARENT_MODULES = 'Modules';
    public const PARENT_PERSONALISATION = 'personalisation';
    public const PARENT_RESOURCES = 'resources';
    public const PARENT_SUDS = 'import/schoolDataImport';
    public const PARENT_SYSTEM_SETTINGS = 'systemSettings';
    public const PARENT_USER_MANAGEMENT = 'userManagement';


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
     * @return array
     */
    public function generate(): array
    {
        return [
            'home' => [
                'name' => 'Home',
                'identifier' => self::PARENT_HOME,
                'visible' =>  true,
                'children' => []
            ],
            'calendar' => [
                'name' => 'Calendar',
                'identifier' => self::PARENT_CALENDAR,
                'visible' =>  true,
                'children' => [
                    'calendar/Cleanup' => [
                        'name' => 'Cleanup',
                        'identifier' => self::CHILD_CALENDAR_CLEANUP,
                        'visible' =>  true,
                        'children' => []
                    ],
                    'calendar/Import' => [
                        'name' => 'Import',
                        'identifier' => self::CHILD_CALENDAR_IMPORT,
                        'visible' =>  true,
                        'children' => []
                    ],
                    'calendar/Subscribe' => [
                        'name' => 'Subscribe',
                        'identifier' => self::CHILD_CALENDAR_SUBSCRIBE,
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'engagement' => [
                'name' => 'Engagement',
                'identifier' => self::PARENT_ENGAGEMENT,
                'visible' =>  true,
                'children' => [
                    'engagement/index/collaboration' => [
                        'name' => 'Collaboration',
                        'identifier' => self::CHILD_ENGAGEMENT_COLLABORATION,
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/components' => [
                        'name' => 'Component Usage',
                        'identifier' => self::CHILD_ENGAGEMENT_COMPONENT_USAGE,
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/dueWork' => [
                        'name' => 'Due Work',
                        'identifier' => self::CHILD_ENGAGEMENT_DUE_WORK,
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/files' => [
                        'name' => 'Files',
                        'identifier' => self::CHILD_ENGAGEMENT_FILES,
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/fileTypes' => [
                        'name' => 'File Types',
                        'identifier' => self::CHILD_ENGAGEMENT_FILETYPES,
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/links' => [
                        'name' => 'Links',
                        'identifier' => self::CHILD_ENGAGEMENT_LINKS,
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/login' => [
                        'name' => 'Login',
                        'identifier' => self::CHILD_ENGAGEMENT_LOGIN,
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/mobile' => [
                        'name' => 'Mobile Apps',
                        'identifier' => self::CHILD_ENGAGEMENT_MOBILE_APPS,
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/news' => [
                        'name' => 'News Posted',
                        'identifier' => self::CHILD_ENGAGEMENT_NEWS_POSTED,
                        'visible' =>  true,
                        'children' => []
                    ],
                    'engagement/index/quiz' => [
                        'name' => 'Quizzes',
                        'identifier' => self::CHILD_ENGAGEMENT_QUIZZES,
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'eportfolio' => [
                'name' => 'ePortfolio',
                'identifier' => self::PARENT_EPORTFOLIO,
                'visible' =>  true,
                'children' => [
                    'eportfolio' => [
                        'name' => 'Templates',
                        'identifier' => self::CHILD_EPORTFOLIO_TEMPLATES,
                        'visible' =>  true,
                        'children' => []
                    ],
                    'eportfolio/deploy' => [
                        'name' => 'Deployment',
                        'identifier' => self::CHILD_EPORTFOLIO_DEPLOYMENT,
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'filters' => [
                'name' => 'Filters',
                'identifier' => self::PARENT_FILTERS,
                'visible' =>  true,
                'children' => []
            ],
            'homepage' => [
                'name' => 'Homepage Templates',
                'identifier' => self::PARENT_HOMEPAGE,
                'visible' =>  true,
                'children' => []
            ],
            'import/directImport' => [
                'name' => 'Import',
                'identifier' => self::PARENT_IMPORT,
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
                'identifier' => self::PARENT_LMS,
                'visible' =>  true,
                'children' => [
                    'terms' => [
                        'name' => 'Academic Period',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'rollover' => [
                        'name' => 'Class Rollover',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'course-builder' => [
                        'name' => 'Course Builder',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Document+Management' => [
                        'name' => 'Document Management',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'grades' => [
                        'name' => 'Grades',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/LMS+Policy' => [
                        'name' => 'LMS Policy',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lti' => [
                        'name' => 'LTI Providers',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'rubric-results' => [
                        'name' => 'Rubrics',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'lists' => [
                'name' => 'Lists',
                'identifier' => self::PARENT_LISTS,
                'visible' =>  true,
                'children' => [
                    'lists/achievement-category' => [
                        'name' => 'Badge Categories',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/campus' => [
                        'name' => 'Campus',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/component-definition' => [
                        'name' => 'Components',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/webdav' => [
                        'name' => 'Drives',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/event-type' => [
                        'name' => 'Event Types',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/house' => [
                        'name' => 'Houses',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/evidence-tag' => [
                        'name' => 'Learning Moments',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/news-topic' => [
                        'name' => 'News Topics',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/pastoral-severity' => [
                        'name' => 'Pastoral Record Severity',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/pastoral-tags' => [
                        'name' => 'Pastoral Record Tags',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/pastoral-document-classification' => [
                        'name' => 'Pastoral SIS Document Classifications',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/pastoral-category' => [
                        'name' => 'Pastoral Sub-Types',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/pastoral-type' => [
                        'name' => 'Pastoral Types',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/residential-house' => [
                        'name' => 'Residential Houses',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/roles' => [
                        'name' => 'Roles',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/submission-errors' => [
                        'name' => 'Submission States',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/timetable-colour' => [
                        'name' => 'Timetable Colours',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/user-title' => [
                        'name' => 'User Titles',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/submission-type' => [
                        'name' => 'Work Types',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'lists/year-levels' => [
                        'name' => 'Year Levels',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'setting/Modules' => [
                'name' => 'Modules',
                'identifier' => self::PARENT_MODULES,
                'visible' =>  true,
                'children' => [
                    'setting/Board+Leave' => [
                        'name' => 'Board Leave',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'feature-tours' => [
                        'name' => 'Feature Tours',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'package' => [
                        'name' => 'Packages',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Pastoral+Care' => [
                        'name' => 'Pastoral Care',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'news' => [
                'name' => 'News',
                'identifier' => '',
                'visible' =>  true,
                'children' => []
            ],
            'messages' => [
                'name' => 'Notifications',
                'identifier' => self::PARENT_MESSAGES,
                'visible' =>  true,
                'children' => [
                    'messages/apps' => [
                        'name' => 'Delivery Providers',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'messages/puush' => [
                        'name' => 'Mobile App Registrations',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'messages/configure' => [
                        'name' => 'Settings',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'personalisation' => [
                'name' => 'Personalisation',
                'identifier' => self::PARENT_PERSONALISATION,
                'visible' =>  true,
                'children' => [
                    'dashboard' => [
                        'name' => 'Dashboard',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'profile-button' => [
                        'name' => 'Profile Buttons',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'menu-side' => [
                        'name' => 'Side Menu',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Skin+Config' => [
                        'name' => 'Skin Config',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'terminology' => [
                        'name' => 'Terminology',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'menu-top' => [
                        'name' => 'Top Menu',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'setting/Resources' => [
                'name' => 'Resources',
                'identifier' => self::PARENT_RESOURCES,
                'visible' =>  true,
                'children' => [
                    'allowed-file' => [
                        'name' => 'Allowed File',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'video' => [
                        'name' => 'Media Queue',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Resources' => [
                        'name' => 'Settings',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'restore' => [
                        'name' => 'Restore',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'usage' => [
                        'name' => 'Usage',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'import/schoolDataImport' => [
                'name' => 'School Data Import',
                'identifier' => self::PARENT_SUDS,
                'visible' =>  $this->configLoader->get('external_type') === 'schoolbox',
                'children' => []
            ],
            'setting' => [
                'name' => 'System Settings',
                'identifier' => self::PARENT_SYSTEM_SETTINGS,
                'visible' =>  true,
                'children' => [
                    'setting/3rd+Party+Services' => [
                        'name' => '3rd Party Services',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Debugging' => [
                        'name' => 'Debugging',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Email' => [
                        'name' => 'Email',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/External+DB' => [
                        'name' => 'External DB',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/LDAP' => [
                        'name' => 'LDAP',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Localisation' => [
                        'name' => 'Localisation',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'oauth' => [
                        'name' => 'oAuth Provider Configuration',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Paths' => [
                        'name' => 'Paths',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Policy' => [
                        'name' => 'Policy',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Proxy' => [
                        'name' => 'Proxy',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/SAML' => [
                        'name' => 'SAML',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Security' => [
                        'name' => 'Security',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'setting/Site' => [
                        'name' => 'Site',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            'user' => [
                'name' => 'User Management',
                'identifier' => self::PARENT_USER_MANAGEMENT,
                'visible' =>  true,
                'children' => [
                    'reports/activity/all' => [
                        'name' => 'Activity Report',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'user/bulk/modify' => [
                        'name' => 'Bulk Modify',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'reports/login/all' => [
                        'name' => 'Login Log',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'shaps' => [
                        'name' => 'Schoolbox Help Provisioning',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                    'ups' => [
                        'name' => 'User Provisioning System',
                        'identifier' => '',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
        ];
    }


    /**
     * Render the self-contained subnav as a purely html fragment
     * so you can slam it straight into a phplib sigh
     *
     * @param string      $pageCurrent
     * @param string|null $subpageCurrent
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function generateHtml(string $pageCurrent, ?string $subpageCurrent): string
    {
        $paths[] = SITE_root . '/library/Schoolbox/Admin/Navigation/templates';

        $options = [
            'debug'            => $this->configLoader->get('debug_templates'),
            'strict_variables' => $this->configLoader->get('strict_templates'),
        ];

        // do not cache when in debug mode
        if (!$this->configLoader->get('debug_mode')) {
            $options['cache'] = $this->configLoader->get('twig_cache_dir');
        }

        $twig = new Environment(new FilesystemLoader($paths), $options);

        return $twig->render('subnav.twig', [
            'adminSubnav' => $this->generate(),
            'pageCurrent' => $pageCurrent,
            'subpageCurrent' => (string)$subpageCurrent
        ]);
    }


    /**
     * Shank the html menu directly into a phplib
     *
     * @param \Sitepage   $page
     * @param string      $pageCurrent
     * @param string|null $subpageCurrent
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function generatePhplib(\Sitepage $page, string $pageCurrent, ?string $subpageCurrent): void
    {
        $page->setVar([
            'ADMIN_NAV' => $this->generateHtml($pageCurrent, $subpageCurrent)
        ]);
    }
}