<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Navigation;

use Schoolbox\Cdn\PrefixGenerator;
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

    /**
     * For access to booleans that determine availability/visibility of certain menu
     * items based on plan, configuration, etc.
     *
     * @var IConfigLoader
     */
    private IConfigLoader $configLoader;


    /**
     * A twig rendering environment. I know it's spawned in the constructor, but half
     * the screens use phplib and the other half use twig
     *
     * @var Environment
     */
    private Environment $twig;


    /**
     * Subnav constructor.
     *
     * @param IConfigLoader $configLoader
     */
    public function __construct(IConfigLoader $configLoader)
    {
        $this->configLoader = $configLoader;

        $paths[] = SITE_root . '/library/Schoolbox/Admin/Navigation/templates';
        $paths[] = SITE_root . '/templates/core/sub';

        $options = [
            'debug'            => $this->configLoader->get('debug_templates'),
            'strict_variables' => $this->configLoader->get('strict_templates'),
        ];

        // do not cache when in debug mode
        if (!$this->configLoader->get('debug_mode')) {
            $options['cache'] = $this->configLoader->get('twig_cache_dir');
        }

        $this->twig = new Environment(new FilesystemLoader($paths), $options);

        // The CDN_PREFIX needs to be made available in this Twig instance
        $this->twig->addGlobal('CDN_PREFIX', (new PrefixGenerator($this->configLoader))->generate());
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
        /* Keys of this array uniquely identify a page and its subpages
         * Used to preselect menu options so users have a sense of where they are
         * URLs are semantically unique but sometimes things get a bit weird
         * when the url gets agglutinated with a querystring param e.g. /report/activity/after...
         * since these no longer match hardcoded keys
         *
         * Although, sometimes controllers get these values from a url parameter
         * and won't be explicitly stated as one of these urls, so be mindful
         * that there are multiple ways to match these urls
         */
        return [
            '/adminv2/home' => [
                'name' => 'Home',
                'visible' =>  true,
                'children' => []
            ],
            '/adminv2/calendar/Cleanup' => [
                'name' => 'Calendar',
                'visible' =>  true,
                'children' => [
                    '/adminv2/calendar/Cleanup' => [
                        'name' => 'Cleanup',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/calendar/Import' => [
                        'name' => 'Import',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/calendar/Subscribe' => [
                        'name' => 'Subscribe',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            '/adminv2/engagement/index/collaboration' => [
                'name' => 'Engagement',
                'visible' =>  true,
                'children' => [
                    '/adminv2/engagement/index/collaboration' => [
                        'name' => 'Collaboration',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/engagement/index/components' => [
                        'name' => 'Component Usage',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/engagement/index/dueWork' => [
                        'name' => 'Due Work',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/engagement/index/files' => [
                        'name' => 'Files',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/engagement/index/fileTypes' => [
                        'name' => 'File Types',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/engagement/index/links' => [
                        'name' => 'Links',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/engagement/index/login' => [
                        'name' => 'Login',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/engagement/index/mobile' => [
                        'name' => 'Mobile Apps',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/engagement/index/news' => [
                        'name' => 'News Posted',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/engagement/index/quiz' => [
                        'name' => 'Quizzes',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            '/adminv2/eportfolio' => [
                'name' => 'ePortfolio',
                'visible' =>  true,
                'children' => [
                    '/adminv2/eportfolio/deploy' => [
                        'name' => 'Deployment',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/eportfolio' => [
                        'name' => 'Templates',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            '/adminv2/filters' => [
                'name' => 'Filters',
                'visible' =>  true,
                'children' => [
                    '/adminv2/filters/assessmentCal' => [
                        'name' => 'Assessments Calendar',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/filters/badgeList' => [
                        'name' => 'Badge List',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/filters/classMarkbook' => [
                        'name' => 'Class Markbook',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/filters/courseMarkbook' => [
                        'name' => 'Course Markbook',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/filters/dueWorkCal' => [
                        'name' => 'Upcoming Due Work',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/filters/grades' => [
                        'name' => 'Grades',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/filters/pending' => [
                        'name' => 'Pending Assessments',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/filters/rubricMarkbook' => [
                        'name' => 'Rubric Markbook',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            '/adminv2/import/directImport' => [
                'name' => 'Import',
                'visible' =>  true,
                'children' => [
                    '/adminv2/import/directImport' => [
                        'name' => 'Home',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/import/folder' => [
                        'name' => 'Pages',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/import/pastoral' => [
                        'name' => 'Pastoral Care',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/import/photos/upload' => [
                        'name' => 'Portrait Photos',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/import/userAccount' => [
                        'name' => 'User Accounts',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            '/adminv2/terms' => [
                'name' => 'LMS',
                'visible' =>  true,
                'children' => [
                    '/adminv2/terms' => [
                        'name' => 'Academic Period',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/rollover' => [
                        'name' => 'Class Rollover',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/course-builder' => [
                        'name' => 'Course Builder',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/Document%20Management' => [
                        'name' => 'Document Management',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/grades' => [
                        'name' => 'Grades',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/LMS%20Policy' => [
                        'name' => 'LMS Policy',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lti' => [
                        'name' => 'LTI Providers',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/rubric-results' => [
                        'name' => 'Rubrics',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            '/adminv2/lists/achievement-category' => [
                'name' => 'Lists',
                'visible' =>  true,
                'children' => [
                    '/adminv2/lists/achievement-category' => [
                        'name' => 'Badge Categories',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/campus' => [
                        'name' => 'Campus',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/component-definition' => [
                        'name' => 'Components',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/webdav' => [
                        'name' => 'Drives',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/event-type' => [
                        'name' => 'Event Types',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/house' => [
                        'name' => 'Houses',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/evidence-tag' => [
                        'name' => 'Learning Moments',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/news-topic' => [
                        'name' => 'News Topics',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/pastoral-severity' => [
                        'name' => 'Pastoral Record Severity',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/pastoral-tags' => [
                        'name' => 'Pastoral Record Tags',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/pastoral-document-classification' => [
                        'name' => 'Pastoral SIS Document Classifications',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/pastoral-category' => [
                        'name' => 'Pastoral Sub-Types',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/pastoral-type' => [
                        'name' => 'Pastoral Types',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/residential-house' => [
                        'name' => 'Residential Houses',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/roles' => [
                        'name' => 'Roles',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/submission-errors' => [
                        'name' => 'Submission States',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/timetable-colour' => [
                        'name' => 'Timetable Colours',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/user-title' => [
                        'name' => 'User Titles',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/submission-type' => [
                        'name' => 'Work Types',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/lists/year-levels' => [
                        'name' => 'Year Levels',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            '/adminv2/setting/Modules' => [
                'name' => 'Modules',
                'visible' =>  true,
                'children' => [
                    '/adminv2/setting/Modules' => [
                        'name' => 'Home',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/Board%20Leave' => [
                        'name' => 'Board Leave',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/feature-tours' => [
                        'name' => 'Feature Tours',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/package' => [
                        'name' => 'Packages',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/Pastoral%20Care' => [
                        'name' => 'Pastoral Care',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            '/adminv2/news' => [
                'name' => 'News',
                'visible' =>  true,
                'children' => []
            ],
            '/adminv2/messages' => [
                'name' => 'Notifications',
                'visible' =>  true,
                'children' => [
                    '/adminv2/messages' => [
                        'name' => 'Home',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/messages/apps' => [
                        'name' => 'Delivery Providers',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/messages/puush' => [
                        'name' => 'Mobile App Registrations',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/messages/configure' => [
                        'name' => 'Settings',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            '/adminv2/dashboard' => [
                'name' => 'Personalisation',
                'visible' =>  true,
                'children' => [
                    '/adminv2/dashboard' => [
                        'name' => 'Dashboard Templates',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/homepage' => [
                        'name' => 'Homepage Templates',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/profile-button' => [
                        'name' => 'Profile Buttons',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/menu-side' => [
                        'name' => 'Side Menu',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/Skin%20Config' => [
                        'name' => 'Skin Config',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/terminology' => [
                        'name' => 'Terminology',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/menu-top' => [
                        'name' => 'Top Menu',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            '/adminv2/setting/Resources' => [
                'name' => 'Resources',
                'visible' =>  true,
                'children' => [
                    '/adminv2/allowed-file' => [
                        'name' => 'Allowed Files',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/video' => [
                        'name' => 'Media Queue',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/Resources' => [
                        'name' => 'Settings',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/restore' => [
                        'name' => 'Restore',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/usage' => [
                        'name' => 'Usage',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            '/adminv2/import/schoolDataImport' => [
                'name' => 'School Data Import',
                'visible' => $this->configLoader->get('external_type') === 'schoolbox',
                'children' => [
                    '/adminv2/import/schoolDataImport' => [
                        'name' => 'Home',
                        'visible' => $this->configLoader->get('external_type') === 'schoolbox',
                        'children' => []
                    ],
                    '/adminv2/import/profile' => [
                        'name' => 'User Profiles',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/import/timetableStructure' => [
                        'name' => 'Timetable Structure',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/import/timetable' => [
                        'name' => 'Timetable',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/import/timetableEnrolment' => [
                        'name' => 'Timetable Enrolment',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            '/adminv2/setting' => [
                'name' => 'System Settings',
                'visible' =>  true,
                'children' => [
                    '/adminv2/setting/3rd%20Party%20Services' => [
                        'name' => '3rd Party Services',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/Debugging' => [
                        'name' => 'Debugging',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/Email' => [
                        'name' => 'Email',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/External%20DB' => [
                        'name' => 'External DB',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/LDAP' => [
                        'name' => 'LDAP',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/Localisation' => [
                        'name' => 'Localisation',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/oauth' => [
                        'name' => 'oAuth Provider Configuration',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/Paths' => [
                        'name' => 'Paths',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/Policy' => [
                        'name' => 'Policy',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/Proxy' => [
                        'name' => 'Proxy',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/SAML' => [
                        'name' => 'SAML',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/Security' => [
                        'name' => 'Security',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/setting/Site' => [
                        'name' => 'Site',
                        'visible' =>  true,
                        'children' => []
                    ],
                ]
            ],
            '/adminv2/user' => [
                'name' => 'User Management',
                'visible' =>  true,
                'children' => [
                    '/adminv2/user' => [
                        'name' => 'Home',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/reports/activity/all' => [
                        'name' => 'Activity Report',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/user/bulk/modify' => [
                        'name' => 'Bulk Modify',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/user/create' => [
                        'name' => 'Create User',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/reports/login/all' => [
                        'name' => 'Login Log',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/shaps' => [
                        'name' => 'Schoolbox Help',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/user/stale' => [
                        'name' => 'Remove Users',
                        'visible' =>  true,
                        'children' => []
                    ],
                    '/adminv2/ups' => [
                        'name' => 'User Provisioning System',
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
     * @param string $url
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function generateHtml(string $url): string
    {

        /* Look ahead for $url
         * If it's in a child node, remember its parent and pass that too
         */
        $pageCurrent = '';
        $subpageCurrent = '';

        foreach ($this->generate() as $parentUrl => $parentData) {
            if ($parentUrl === $url) {
                $pageCurrent = $parentUrl;
            }

            if (\count($parentData['children'])) {
                foreach ($parentData['children'] as $childUrl => $childData) {
                    if ($childUrl === $url) {
                        $pageCurrent = $parentUrl;
                        $subpageCurrent = $childUrl;
                    }
                }
            }
        }

        return $this->twig->render('subnav.twig', [
            'adminSubnav' => $this->generate(),
            'pageCurrent' => $pageCurrent,
            'subpageCurrent' => $subpageCurrent
        ]);
    }


    /**
     * Shank the html menu directly into a phplib
     *
     * @param \SitePage   $page
     * @param string      $pageCurrent
     * @return Subnav
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function generatePhplib(\SitePage $page, string $pageCurrent): self
    {
        $page->setVar([
            'ADMIN_NAV' => $this->generateHtml($pageCurrent)
        ]);

        return $this;
    }


    /**
     * Generate breadcrumbs from the adminSubnav stack, for the consistency of names
     * in both the breadcrumbs themselves and also the submenu
     *
     * There are multiple implementations of breadcrumbs but there should be only one
     * Those should be removed in favour of this implementation
     *
     * @param string $url
     * @return array
     */
    public function generateBreadcrumbs(string $url): array
    {
        $allCrumbs = [
            ['Administration', '/adminv2/home']
        ];

        foreach ($this->generate() as $parentUrl => $parentData) {
            if ($parentUrl === $url) {
                $allCrumbs[] = [$parentData['name'], $parentUrl];
            }

            if (\count($parentData['children'])) {
                foreach ($parentData['children'] as $childUrl => $childData) {
                    if ($childUrl === $url) {

                        /* parentUrl is sometimes the same as a childUrl
                         * and manifest twice in the breadcrumb trail; we only
                         * need the parent node if it was different to its child node
                         */
                        if ($parentUrl !== $url) {
                            $allCrumbs[] = [$parentData['name'], $parentUrl];
                        }

                        $allCrumbs[] = [$childData['name'],  $childUrl];
                    }
                }
            }
        }

        return $allCrumbs;
    }


    /**
     * Shank a breadcrumbs html right into a phplib
     *
     * @param \SitePage   $page
     * @param string      $pageCurrent
     * @return Subnav
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function generateBreadcrumbsHtml(\SitePage $page, string $pageCurrent): self
    {
        $page->setVar([
            'ADMIN_BREADCRUMBS' => $this->twig->render('adminBreadcrumbs.twig', [
                'allBreadcrumbs' => $this->generateBreadcrumbs($pageCurrent)
            ])
        ]);

        return $this;
    }
}
