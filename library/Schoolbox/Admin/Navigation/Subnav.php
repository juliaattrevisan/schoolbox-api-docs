<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Navigation;

use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\L10n\Mapper as L10nMapper;
use Schoolbox\L10n\RosettaStoneFactory;
use Schoolbox\Config\Type\Model as ConfigType;
use Schoolbox\Filter\Page\AvailabilityChecker;
use Schoolbox\Filter\Page\LabelBuilder;
use Schoolbox\Filter\Page\Model as FilterPageModel;
use Schoolbox\IConfigLoader;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

use function count;

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
     * @var User The logged-in user.
     */
    private User $user;

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
     * @param User          $user
     */
    public function __construct(IConfigLoader $configLoader, User $user)
    {
        $this->configLoader = $configLoader;
        $this->user = $user;

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
        $this->twig->addExtension(new DebugExtension());

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
     * Note the returned array is a recursively defined structure where the type of each child is the same as the parent.
     * PHPStan array shapes do not allow recursive definitions.  Using an array shape on the top-level only does more
     * harm than good because it gives the illusion of type-safety when child items may not be safe.
     * Note also that 'visible' is compulsory due to awkwardness in handling casting and null-coalescence in Twig.
     * See https://github.com/alaress/schoolbox/pull/31321.
     * @return array (with keys: {name: string, visible: boolean, searchable?: boolean, description?: string, children?: array})
     */
    public function generate(): array
    {
        // Get configs which are not hidden or custom, and have a type.
        // Any setting pages which have no visible configs are hidden.
        // TODO inject the config mapper either here or in the constructor
        //      (loader will not suffice, I need to query the config DB)
        $configMapper = ConfigMapper::getInstance();
        $pb           = $configMapper->getPredicateBuilder()
            ->equals('hidden', 'false')
            ->notEquals('module', 'custom')
            ->notNull('type_id');
        $enabledConfigs = $configMapper->findByPredicates($pb)
            ->filter(
                function (Config $config) {
                    return $config->getType() instanceof ConfigType;
                }
            )
            ->groupByCallback(
                function (Config $config) {
                    return $config->getType() instanceof ConfigType
                        ? $config->getType()->getId()
                        : 0;
                }
            );

        $filterAvailabilityChecker = new AvailabilityChecker($this->configLoader);
        $filterLabelBuilder        = new LabelBuilder();

        $userIsSuperuser = $this->user->isSuperuser();
        $userHasServicePermissionNews = (
            $this->user->hasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_FULL)
        );
        $userHasServicePermissionCalendar = (
            $this->user->hasServicePermission(UserAccess::SERVICE_CALENDAR, UserAccess::ACCESS_FULL)
        );
        $userHasServicePermissionPastoral = (
            $this->user->hasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL)
        );
        $userHasServicePermissionLms = (
            $this->user->hasServicePermission(UserAccess::SERVICE_LMS, UserAccess::ACCESS_FULL)
        );
        $userHasServicePermissionBadge = (
            $this->user->hasServicePermission(UserAccess::SERVICE_BADGE, UserAccess::ACCESS_FULL)
        );
        $userHasServicePermissionCms = (
            $this->user->hasServicePermission(UserAccess::SERVICE_CMS, UserAccess::ACCESS_FULL)
        );
        $userHasServicePermissionDesign = (
            $this->user->hasServicePermission(UserAccess::SERVICE_DESIGN, UserAccess::ACCESS_FULL)
        );
        $userHasServicePermissionTimetable = (
            $this->user->hasServicePermission(UserAccess::SERVICE_TIMETABLE, UserAccess::ACCESS_FULL)
        );
        $userHasAnyModeratorServicePermission = (
            $userHasServicePermissionNews ||
            $userHasServicePermissionCalendar ||
            $userHasServicePermissionPastoral ||
            $userHasServicePermissionLms ||
            $userHasServicePermissionBadge ||
            $userHasServicePermissionCms ||
            $userHasServicePermissionDesign ||
            $userHasServicePermissionTimetable
        );

        /* Keys of this array uniquely identify a page and its subpages
         * Used to preselect menu options so users have a sense of where they are
         * URLs are semantically unique but sometimes things get a bit weird
         * when the url gets agglutinated with a querystring param e.g. /report/activity/after...
         * since these no longer match hardcoded keys
         *
         * Although, sometimes controllers get these values from a url parameter
         * and won't be explicitly stated as one of these urls, so be mindful
         * that there are multiple ways to match these urls
         *
         * Ordering of menu items
         * ----------------------
         * Menu items should be ordered according to the following rules:
         *  - Items named 'Home' go at the top, whether in the main menu or sub-menus.
         *  - Except for items named 'Home', menu items should be ordered alphabetically.
         *  - Configurable terminology should not be used (because that would mess up alphabetical ordering).
         */
        return [
            '/adminv2/home' => [
                'name'        => 'Home',
                'visible'     => $userIsSuperuser || $userHasAnyModeratorServicePermission,
                'description' => 'Key system status information.',
                'children'    => [],
            ],
            '/adminv2/badge' => [
                'name'       => 'Badges',
                'visible'    =>  (
                    ($userIsSuperuser || $userHasServicePermissionBadge) &&
                    $this->configLoader->get(ConfigMapper::NAME_BADGES_ENABLED)
                ),
                'searchable' => false,
                'children'   => [
                    '/adminv2/filters/badge/badgeList' => [
                        'name'        => $filterLabelBuilder->buildLabel(FilterPageModel::PAGE_BADGE_LIST) . ' Filter',
                        'visible'     => $filterAvailabilityChecker->isAvailable(FilterPageModel::PAGE_BADGE_LIST),
                        'description' => 'Configure the default and available filter options on the goal and achievements search page.',
                    ],
                    '/adminv2/filters/badge/awardList' => [
                        'name'        => $filterLabelBuilder->buildLabel(FilterPageModel::PAGE_AWARD_LIST) . ' Filter',
                        'visible'     => $filterAvailabilityChecker->isAvailable(FilterPageModel::PAGE_AWARD_LIST),
                        'description' => 'Configure the default and available filter options on the goal and achievements search page.',
                    ],
                    '/adminv2/lists/badge/achievement-category' => [
                        'name'        => 'Categories List',
                        'visible'     => true,
                        'description' => 'Configure the list of categories that badges can be defined within.',
                    ],
                ]
            ],
            '/adminv2/calendar' => [
                'name'       => 'Calendar',
                'visible'    => $userIsSuperuser || $userHasServicePermissionCalendar,
                'searchable' => false,
                'children'   => [
                    '/adminv2/calendar/Cleanup' => [
                        'name'        => 'Cleanup',
                        'visible'     => true,
                        'description' => 'A tool to bulk or individually remove events from personal or group calendars.',
                    ],
                    '/adminv2/lists/calendar/event-type' => [
                        'name'        => 'Event Types',
                        'visible'     => true,
                        'description' => 'Setup the list of event types for filtering and colour coding.',
                    ],
                    '/adminv2/calendar/Import' => [
                        'name'        => 'Import',
                        'visible'     => true,
                        'description' => 'Import events into the school calendar via ICS or CSV.',
                    ],
                    '/adminv2/calendar/Subscribe' => [
                        'name'        => 'Subscribe',
                        'visible'     => true,
                        'description' => 'Control which additional calendars are included into a users calendar. This includes VCAL events, modules and the SIS.',
                    ],
                ],
            ],
            '/adminv2/design' => [
                'name'       => 'Design',
                'visible'    => $userIsSuperuser || $userHasServicePermissionDesign,
                'searchable' => false,
                'children'   => [
                    '/adminv2/dashboard' => [
                        'name'        => 'Dashboard Templates',
                        'visible'     => true,
                        'description' => 'Configure the layout of the initial landing page when you first login for each role.',
                    ],
                    '/adminv2/menu-side' => [
                        'name'        => 'Side Menu',
                        'visible'     => true,
                        'description' => 'Add and remove links to the hierarchical navigation menu on the left hand side of the screen.',
                    ],
                    '/adminv2/setting/design/Skin%20Config' => [
                        'name'        => 'Skin Config',
                        'visible'     => true,
                        'description' => 'Configure the look and feel of your system, including colours, images and logos.',
                    ],
                    '/adminv2/lists/design/timetable-colour' => [
                        'name'        => 'Timetable Colours List',
                        'visible'     => true,
                        'description' => 'Configure the set of colours used to randomly colour the timetable.',
                    ],
                    '/adminv2/menu-top' => [
                        'name'        => 'Top Menu',
                        'visible'     => true,
                        'description' => 'Configure the top menu navigation and give quick access to the most important features.',
                    ],
                ]
            ],
            '/adminv2/engagement/index/collaboration' => [
                'name'       => 'Engagement',
                'visible'    => $userIsSuperuser,
                'searchable' => false,
                'children'   => [
                    '/adminv2/engagement/index/collaboration' => [
                        'name'        => 'Collaboration',
                        'visible'     => true,
                        'description' => 'Statistics on how users are using the collaboration features like forums and social feeds.',
                    ],
                    '/adminv2/engagement/index/components' => [
                        'name'        => 'Component Usage',
                        'visible'     => true,
                        'description' => 'Statistics on which homepage components are currently in use.',
                    ],
                    '/adminv2/engagement/index/dueWork' => [
                        'name'        => 'Due Work',
                        'visible'     => (bool)$this->configLoader->get(ConfigMapper::NAME_LMS_ENABLED),
                        'description' => 'Statistics on how much the LMS features are being utilised including feedback and online submissions.',
                    ],
                    '/adminv2/engagement/index/files' => [
                        'name'        => 'Files',
                        'visible'     => true,
                        'description' => 'Statistics on how many files are being created and accessed by different roles within the system.',
                    ],
                    '/adminv2/engagement/index/links' => [
                        'name'        => 'Links',
                        'visible'     => true,
                        'description' => 'Statistics on how many links are being created and accessed by different roles within the system.',
                    ],
                    '/adminv2/engagement/index/login' => [
                        'name'        => 'Login',
                        'visible'     => true,
                        'description' => 'Statistics on the total logins by role and also the percentage of users who have logged in at all.',
                    ],
                    '/adminv2/engagement/index/fileTypes' => [
                        'name'        => 'Media',
                        'visible'     => true,
                        'description' => 'Statistics on how many videos and images are being viewed within the system.',
                    ],
                    '/adminv2/engagement/index/mobile' => [
                        'name'        => 'Mobile Apps',
                        'visible'     => (bool)$this->configLoader->get(ConfigMapper::NAME_NOTIFICATIONS_ENABLED),
                        'description' => 'Statistics related to the use of mobile apps by users to access the system.',
                    ],
                    '/adminv2/engagement/index/news' => [
                        'name'        => 'News Posted',
                        'visible'     => true,
                        'description' => 'Statistics regarding the amount of news being created within the system.',
                    ],
                    '/adminv2/engagement/index/quiz' => [
                        'name'        => 'Quizzes',
                        'visible'     => true,
                        'description' => 'Statistics on how many quizzes have been created and attempted.',
                    ],
                ],
            ],
            '/adminv2/eportfolio' => [
                'name'       => 'ePortfolio',
                'visible'    => $userIsSuperuser && $this->configLoader->get(ConfigMapper::NAME_EPORTFOLIO_ENABLED),
                'searchable' => false,
                'children'   => [
                    '/adminv2/eportfolio/deploy' => [
                        'name'        => 'Deployment',
                        'visible'     => true,
                        'description' => 'Configure and setup the scaffolding templates that users can create ePortfolio pages from.',
                    ],
                    '/adminv2/setting/ePortfolio' => [
                        'name'        => 'Policy',
                        'visible'     => true,
                        'description' => 'Configure policies relating to ePortfolio.',
                    ],
                    '/adminv2/eportfolio' => [
                        'name'        => 'Templates',
                        'visible'     => true,
                        'description' => 'Clone and deploy an ePortfolio page from one user to many other users.',
                    ],
                ],
            ],
            '/adminv2/lms' => [
                'name'       => 'LMS',
                'visible'    => $userIsSuperuser || $userHasServicePermissionLms,
                'searchable' => false,
                'children'   => [
                    '/adminv2/terms' => [
                        'name'        => 'Academic Period',
                        'visible'     => (bool)$this->configLoader->get(ConfigMapper::NAME_LMS_ENABLED),
                        'description' => 'Configure the blocks of time that assessments will be visible within, often called terms or semesters.',
                    ],
                    '/adminv2/filters/lms/assessmentCal' => [
                        'name'        => $filterLabelBuilder->buildLabel(FilterPageModel::PAGE_ASSESSMENT_CALENDAR) . ' Filter',
                        'visible'     => $filterAvailabilityChecker->isAvailable(FilterPageModel::PAGE_ASSESSMENT_CALENDAR),
                        'description' => 'Configure the default and available filter options on the assessment calendar.',
                    ],
                    '/adminv2/bulkCourseImport' => [
                        'name'        => 'Bulk Course Import',
                        'visible'     => (bool)$this->configLoader->get(ConfigMapper::NAME_LMS_ENABLED),
                        'description' => '',
                    ],
                    '/adminv2/filters/lms/classMarkbook' => [
                        'name'        => $filterLabelBuilder->buildLabel(FilterPageModel::PAGE_CLASS_MARK_BOOK) . ' Filter',
                        'visible'     => (
                            $filterAvailabilityChecker->isAvailable(FilterPageModel::PAGE_CLASS_MARK_BOOK) &&
                            $this->configLoader->get(ConfigMapper::NAME_LMS_ENABLED)
                        ),
                        'description' => 'Configure the default and available filter options on the class markbook.',
                    ],
                    '/adminv2/course-builder' => [
                        'name'        => 'Course Framework',
                        'visible'     => (bool)$this->configLoader->get(ConfigMapper::NAME_LMS_ENABLED),
                        'description' => 'Configure the default page templates and permissions for course and unit pages.',
                    ],
                    '/adminv2/filters/lms/courseMarkbook' => [
                        'name'        => $filterLabelBuilder->buildLabel(FilterPageModel::PAGE_COURSE_MARK_BOOK) . ' Filter',
                        'visible'     => (
                            $filterAvailabilityChecker->isAvailable(FilterPageModel::PAGE_COURSE_MARK_BOOK) &&
                            $this->configLoader->get(ConfigMapper::NAME_LMS_ENABLED)
                        ),
                        'description' => 'Configure the default and available filter options on the course markbook.',
                    ],
                    '/adminv2/setting/lms/Document%20Management' => [
                        'name'        => 'Document Management',
                        'visible'     => (
                            count($enabledConfigs[ConfigMapper::TYPE_DOC_MANAGE] ?? []) > 0 &&
                            $this->configLoader->get('external_type') === 'synergetic'
                        ),
                        'description' => 'Configure which Synergetic DocMan documents are visible via the Academic Reports button on the students profile.',
                    ],
                    '/adminv2/grades' => [
                        'name'        => 'Grades',
                        'visible'     => (bool)$this->configLoader->get(ConfigMapper::NAME_LMS_ENABLED),
                        'description' => 'Configure the available grade scales that can be used for assessment and marking.',
                    ],
                    '/adminv2/filters/lms/grades' => [
                        'name'        => $filterLabelBuilder->buildLabel(FilterPageModel::PAGE_GRADES) . ' Filter',
                        'visible'     => (
                            $filterAvailabilityChecker->isAvailable(FilterPageModel::PAGE_GRADES) &&
                            $this->configLoader->get(ConfigMapper::NAME_LMS_ENABLED)
                        ),
                        'description' => 'Configure the default and available filter options on the continuous report / class grades page.',
                    ],
                    '/adminv2/lists/evidence-tag' => [
                        'name'        => 'Learning Moments',
                        'visible'     => (
                            $userIsSuperuser &&
                            $this->configLoader->get(ConfigMapper::NAME_LEARNING_MOMENTS_ENABLED)
                        ),
                        'description' => 'Configure tags that can be used on learning moments when students capture evidence.',
                    ],
                    '/adminv2/lesson-plan/template' => [
                        'name'        => 'Lesson Plan Templates',
                        'visible'     => (bool)$this->configLoader->get(ConfigMapper::NAME_LMS_ENABLED),
                        'description' => 'Configure Lesson Plan Templates',
                    ],
                    '/adminv2/setting/lms/LMS%20Policy' => [
                        'name'        => 'LMS Policy',
                        'visible'     => (
                            count($enabledConfigs[ConfigMapper::TYPE_LMS_POLICY] ?? []) > 0 &&
                            $this->configLoader->get(ConfigMapper::NAME_LMS_ENABLED)
                        ),
                        'description' => 'Configure policy related to continuous reporting, grades, due work, assessment and feedback.',
                    ],
                    '/adminv2/lti' => [
                        'name'        => 'LTI Tools',
                        'visible'     => true,
                        'description' => 'Configure the 3rd party LTI integrations that you wish to make available for use in the LTI component.',
                    ],
                    '/adminv2/filters/lms/pending' => [
                        'name'        => $filterLabelBuilder->buildLabel(FilterPageModel::PAGE_PENDING_ASSESSMENTS) . ' Filter',
                        'visible'     => $filterAvailabilityChecker->isAvailable(FilterPageModel::PAGE_PENDING_ASSESSMENTS),
                        'description' => 'Configure the default and available filter options on the pending assessments page.',
                    ],
                    '/adminv2/filters/lms/rubricMarkbook' => [
                        'name'        => $filterLabelBuilder->buildLabel(FilterPageModel::PAGE_RUBRIC_MARK_BOOK) . ' Filter',
                        'visible'     => (
                            $filterAvailabilityChecker->isAvailable(FilterPageModel::PAGE_RUBRIC_MARK_BOOK) &&
                            $this->configLoader->get(ConfigMapper::NAME_LMS_ENABLED)
                        ),
                        'description' => 'Configure the default and available filter options on the class rubric markbook.',
                    ],
                    '/adminv2/rubric-result-set' => [
                        'name'        => 'Rubric Result Sets',
                        'visible'     => (bool)$this->configLoader->get(ConfigMapper::NAME_LMS_ENABLED),
                        'description' => 'Setup quick fill results for rubrics to help quickly build a rubric with generic level descriptors.',
                    ],
                    '/adminv2/summative-report' => [
                        'name'        => 'Summative Reports',
                        'visible'     => (bool)$this->configLoader->get(ConfigMapper::NAME_SUMMATIVE_REPORT_ENABLED),
                        'description' => 'Configure a batch of student summative reports to be printed to PDF for reporting purposes.',
                    ],
                    '/adminv2/lists/lms/submission-errors' => [
                        'name'        => 'Submission States List',
                        'visible'     => (bool)$this->configLoader->get(ConfigMapper::NAME_LMS_ENABLED),
                        'description' => 'Configure the alternative states an assessment can be in beyond being graded.',
                    ],
                    '/adminv2/filters/lms/dueWorkCal' => [
                        'name'        => $filterLabelBuilder->buildLabel(FilterPageModel::PAGE_UPCOMING_ASSESSMENTS) . ' Filter',
                        'visible'     => (
                            $filterAvailabilityChecker->isAvailable(FilterPageModel::PAGE_UPCOMING_ASSESSMENTS) &&
                            $this->configLoader->get(ConfigMapper::NAME_LMS_ENABLED)
                        ),
                        'description' => 'Configure the default and available filter options on the students due work calendar.',
                    ],
                    '/adminv2/lists/lms/submission-type' => [
                        'name'        => 'Work Types List',
                        'visible'     => (bool)$this->configLoader->get(ConfigMapper::NAME_LMS_ENABLED),
                        'description' => 'Setup the categorisation for assessments, useful for filtering and report creation.',
                    ],
                ],
            ],
            '/adminv2/setting/Modules' => [
                'name'       => 'Modules',
                'visible'    => $userIsSuperuser,
                'children'   => [
                    '/adminv2/setting/Modules' => [
                        'name'        => 'Home',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_MODULES] ?? []) > 0,
                        'description' => 'Control which features and modules are currently available in your system.',
                    ],
                    '/adminv2/feature-tours' => [
                        'name'        => 'Feature Tours',
                        'visible'     => true,
                        'description' => 'Review the current guides that help new users get used to the system.',
                    ],
                    '/adminv2/intellischool/console' => [
                        'name'        => 'Intellischool Admin',
                        'visible'     => (bool)$this->configLoader->get(ConfigMapper::NAME_INTELLISCHOOL_DEPLOYMENT_ID),
                        'description' => 'Configure Intellischool Analytics Integration.',
                    ],
                    '/adminv2/package' => [
                        'name'        => 'Packages',
                        'visible'     => true,
                        'description' => 'Install optional content packages supplied to get you started.',
                    ],
                ],
            ],
            '/adminv2/news' => [
                'name'        => 'News',
                'visible'     => $userIsSuperuser || $userHasServicePermissionNews,
                'searchable'  => false,
                'children'    => [
                    '/adminv2/news' => [
                        'name'        => 'Settings / Policy',
                        'visible'     => true,
                        'description' => 'Configure moderation and default values when posting and modifying news.',
                    ],
                    '/adminv2/lists/news/news-topic' => [
                        'name'        => 'Topics List',
                        'visible'     => true,
                        'description' => 'Configure the list of news topics to allow categorisation of news.',
                    ],
                ],
            ],
            '/adminv2/notifications' => [
                'name'       => 'Notifications',
                'visible'    => $userIsSuperuser && $this->configLoader->get(ConfigMapper::NAME_NOTIFICATIONS_ENABLED),
                'children'   => [
                    '/adminv2/notifications' => [
                        'name'        => 'Home',
                        'visible'     => true,
                        'description' => 'Preview emailed notifications, and set a schedule for the sending of digest notifications.',
                    ],
                    '/adminv2/notifications/apps' => [
                        'name'        => 'Delivery Providers',
                        'visible'     => true,
                        'description' => 'Configure and review how push notifications are delivered to mobile phones.',
                    ],
                    '/adminv2/notifications/puush' => [
                        'name'        => 'Mobile App Registrations',
                        'visible'     => true,
                        'description' => 'Review the list of mobile devices that have registered to receive notifications.',
                    ],
                    '/adminv2/notifications/configure' => [
                        'name'        => 'Settings',
                        'visible'     => true,
                        'description' => 'Configure the global notification settings for all user roles, how messages will be delivered, and what options they can select.',
                    ],
                ],
            ],
            '/adminv2/pastoral' => [
                'name'       => 'Pastoral Care',
                'visible'    => (
                    ($userIsSuperuser || $userHasServicePermissionPastoral) &&
                    $this->configLoader->get(ConfigMapper::NAME_PASTORAL_ENABLED)
                ),
                'searchable' => false,
                'children'   => [
                    '/adminv2/lists/pastoral/pastoral-action' => [
                        'name'        => 'Actions List',
                        'visible'     => (bool)$this->configLoader->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED),
                        'description' => 'Setup the actions of pastoral records.',
                    ],
                    '/pastoral/bulk-import' => [
                        'name'        => 'Bulk Import',
                        'visible'     => true,
                        'description' => 'Import a large number of pastoral records from files within a ZIP',
                    ],
                    '/adminv2/filters/pastoral/classPastoral' => [
                        'name'        => $filterLabelBuilder->buildLabel(FilterPageModel::PAGE_CLASS_PASTORAL) . ' Filter',
                        'visible'     => $filterAvailabilityChecker->isAvailable(FilterPageModel::PAGE_CLASS_PASTORAL),
                        'description' => 'Configure the default and available filter options on the class pastoral report.',
                    ],
                    '/pastoral/management/groups' => [
                        'name'        => 'Groups Management',
                        'visible'     => $userHasServicePermissionPastoral, // Note this page is special in that available to superusers who are not Pastoral Moderators.
                        'description' => 'Manage Groups within the pastoral system',
                    ],
                    '/pastoral/management/audience/' => [
                        'name'        => 'Pastoral Record Settings',
                        'visible'     => $userHasServicePermissionPastoral, // Note this page is special in that available to superusers who are not Pastoral Moderators.
                        'description' => 'Manage Flag, Audience and Template Defaults',
                    ],
                    '/adminv2/lists/pastoral/pastoral-severity' => [
                        'name'        => 'Record Severity List',
                        'visible'     => (bool)$this->configLoader->get('pastoral_severity'),
                        'description' => 'Setup appropriate severity or importance of your pastoral records.',
                    ],
                    '/adminv2/lists/pastoral/pastoral-tags' => [
                        'name'        => 'Record Tags List',
                        'visible'     => true,
                        'description' => 'Maintain tags used on pastoral records, or if setup, lock this list to be the only tags available.',
                    ],
                    '/adminv2/setting/pastoral/Pastoral%20Care' => [
                        'name'        => 'Settings / Policy',
                        'visible'     => true,
                        'description' => 'Configure policy settings related to the pastoral care module.',
                    ],
                    '/adminv2/lists/pastoral/pastoral-document-classification' => [
                        'name'        => 'SIS Document Classifications List',
                        'visible'     => true,
                        'description' => 'Configure how file attachments in pastoral are synchronised back to DocMan in Synergetic.',
                    ],
                    '/adminv2/lists/pastoral/pastoral-category' => [
                        'name'        => 'Sub-Types List',
                        'visible'     => true,
                        'description' => 'Optionally configure further sub types under your existing types for more detailed categorisation of pastoral records.',
                    ],
                    '/adminv2/import/pastoral' => [
                        'name'        => 'Synergetic Import',
                        'visible'     => $this->configLoader->get('external_type') === 'synergetic',
                        'description' => 'Import your pastoral care records from Synergetic.',
                    ],
                    '/adminv2/lists/pastoral/pastoral-type' => [
                        'name'        => 'Types List',
                        'visible'     => true,
                        'description' => 'Setup the types of pastoral records, to control visibility and categorisation..',
                    ],
                ]
            ],
            '/adminv2/cms' => [
                'name'       => 'Resources',
                'visible'    => $userIsSuperuser || $userHasServicePermissionCms,
                'searchable' => false,
                'children'   => [
                    '/adminv2/allowed-file' => [
                        'name'        => 'Allowed Files',
                        'visible'     => true,
                        'description' => 'Define the file mime types that can be uploaded, the maximum filesize and the icon they will use.',
                    ],
                    '/adminv2/rollover' => [
                        'name'        => 'Class Rollover',
                        'visible'     => true,
                        'description' => 'At the end of the year or semester, archive your class pages so you can start fresh the following year.',
                    ],
                    '/adminv2/lists/cms/component-definition' => [
                        'name'        => 'Components List',
                        'visible'     => true,
                        'description' => 'Control which components can be used on homepages, dashboards and ePortfolios.',
                    ],
                    '/adminv2/homepage' => [
                        'name'        => 'Homepage Templates',
                        'visible'     => true,
                        'description' => 'Configure and setup the scaffolding templates that users can create homepages from.',
                    ],
                    '/adminv2/import/folder' => [
                        'name'        => 'Import Pages',
                        'visible'     => true,
                        'description' => 'Import all school class pages via a CSV file.',
                    ],
                    '/adminv2/video' => [
                        'name'        => 'Media Queue',
                        'visible'     => true,
                        'description' => 'A log of videos and audio that is currently being transcoded or failed to transcode.',
                    ],
                    '/adminv2/setting/cms/Resources' => [
                        'name'        => 'Resource Settings',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_RESOURCES] ?? []) > 0,
                        'description' => 'Control how files are stored and processed when uploaded.',
                    ],
                    '/adminv2/restore' => [
                        'name'        => 'Restore',
                        'visible'     => true,
                        'description' => 'Undelete pages, folders and assessments that were accidentally deleted recently.',
                    ],
                    '/adminv2/usage' => [
                        'name'        => 'Usage',
                        'visible'     => true,
                        'description' => 'View what files have been uploaded, by whom and how much disk space they are using.',
                    ],
                ]
            ],
            '/adminv2/schoolDataImport' => [
                'name'       => 'School Data Import',
                'visible'    => (
                    ($userIsSuperuser || $userHasServicePermissionTimetable) &&
                    $this->configLoader->get('external_type') === 'schoolbox'
                ),
                'searchable' => false,
                'children'   => [
                    '/adminv2/import/schoolDataImport' => [
                        'name'        => 'Home',
                        'visible'     => true,
                        'description' => 'Configure your school data (SIS) import process.',
                    ],
                    // Note regarding ordering of School Data Import menu items
                    // --------------------------------------------------------
                    // The ordering below matches the order in which the import operations must be performed in order to avoid errors.
                    '/adminv2/import/profile' => [
                        'name'        => 'Step 1 - User Profiles',
                        'visible'     => $this->configLoader->get('schooldata_import_data_source') === 'csv',
                        'description' => 'Import staff, student, parent and relationship data via CSV files.',
                    ],
                    '/adminv2/import/timetableStructure' => [
                        'name'        => 'Step 2 - Timetable Structure',
                        'visible'     => $this->configLoader->get('schooldata_import_data_source') === 'csv',
                        'description' => 'Import timetable definition and structure via CSV.',
                    ],
                    '/adminv2/import/timetable' => [
                        'name'        => 'Step 3 - Timetable',
                        'visible'     => $this->configLoader->get('schooldata_import_data_source') === 'csv',
                        'description' => 'Import the classes and their periods via CSV.',
                    ],
                    '/adminv2/import/timetableEnrolment' => [
                        'name'        => 'Step 4 - Timetable Enrolment',
                        'visible'     => $this->configLoader->get('schooldata_import_data_source') === 'csv',
                        'description' => 'Import the students and teachers in classes via CSV.',
                    ],
                ],
            ],
            '/adminv2/setting' => [
                'name'       => 'System Settings',
                'visible'    => $userIsSuperuser,
                'searchable' => false,
                'children'   => [
                    '/adminv2/setting/3rd%20Party%20Services' => [
                        'name'        => '3rd Party Services',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_3RD] ?? []) > 0,
                        'description' => 'Enable and configure API connectivity between your platform and many other integrated services.',
                    ],
                    '/adminv2/setting/AI' => [
                        'name'        => 'AI Services',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_3RD] ?? []) > 0,
                        'description' => 'Enable and configure OpenAI integration to enable AI services to be made available to users.',
                    ],
                    '/adminv2/setting/Debugging' => [
                        'name'        => 'Debugging',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_DEBUGGING] ?? []) > 0,
                        'description' => 'Enable developer features for debugging issues and tracing problems.',
                    ],
                    '/adminv2/lists/webdav' => [
                        'name'        => 'Drives',
                        'visible'     => (bool)$this->configLoader->get(ConfigMapper::NAME_WEBDAV_ENABLED),
                        'description' => 'Configure network drives that users will be able to browse inside the platform using the WebDAV protocol.',
                    ],
                    '/adminv2/setting/Email' => [
                        'name'        => 'Email',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_EMAIL] ?? []) > 0,
                        'description' => 'Control how we send email from the platform.',
                    ],
                    '/adminv2/email-dkim' => [
                        'name'        => 'Email DKIM',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_EMAIL] ?? []) > 0,
                        'description' => '',
                    ],
                    '/adminv2/setting/External%20DB' => [
                        'name'        => 'External DB',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_EXTERNAL] ?? []) > 0,
                        'description' => 'Configure the connection to your school database (SIS).',
                    ],
                    '/adminv2/setting/LDAP' => [
                        'name'        => 'LDAP',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_LDAP] ?? []) > 0,
                        'description' => 'Setup the LDAP servers to provide authentication services for users.',
                    ],
                    '/adminv2/setting/Localisation' => [
                        'name'        => 'Localisation',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_LOCALISATION] ?? []) > 0,
                        'description' => 'Customise dates and other local formatting options.',
                    ],
                    '/adminv2/oauth/client' => [
                        'name'        => 'oAuth Client Configuration',
                        'visible'     => true,
                        'description' => 'Configure oAuth/OIDC clients which may use this Schoolbox instance as an identity provider.',
                    ],
                    '/adminv2/oauth' => [
                        'name'        => 'oAuth Provider Configuration',
                        'visible'     => true,
                        'description' => 'Configure an oAuth endpoint to validate tokens provided by mobile apps.',
                    ],
                    '/adminv2/setting/Paths' => [
                        'name'        => 'Paths',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_PATHS] ?? []) > 0,
                        'description' => 'Displays the location where files are stored on disk.',
                    ],
                    '/adminv2/setting/Policy' => [
                        'name'        => 'Policy',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_POLICY] ?? []) > 0,
                        'description' => 'Set a wide variety of policy settings that will control the behaviour and default settings for many features.',
                    ],
                    '/adminv2/setting/Proxy' => [
                        'name'        => 'Proxy',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_PROXY] ?? []) > 0,
                        'description' => 'If required, you can configure the system to complete internet HTTP requests via a proxy server.',
                    ],
                    '/adminv2/setting/SAML' => [
                        'name'        => 'SAML',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_SAML] ?? []) > 0,
                        'description' => 'Configure your single sign-on identity provider.',
                    ],
                    '/adminv2/setting/Security' => [
                        'name'        => 'Security',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_SECURITY] ?? []) > 0,
                        'description' => 'Control security related to authentication, logins and embedding of 3rd party content.',
                    ],
                    '/adminv2/setting/Site' => [
                        'name'        => 'Site',
                        'visible'     => count($enabledConfigs[ConfigMapper::TYPE_SITE] ?? []) > 0,
                        'description' => 'Setup and configure global settings related to the site, like name, domain and analytics.',
                    ],
                    '/adminv2/terminology' => [
                        'name'        => 'Terminology',
                        'visible'     => true,
                        'description' => 'Localise the words used to describe key features to your own language.',
                    ],
                ],
            ],
            '/adminv2/user' => [
                'name'       => 'User Management',
                'visible'    => $userIsSuperuser,
                'children'   => [
                    '/adminv2/user' => [
                        'name'        => 'Home',
                        'visible'     => true,
                        'description' => 'Overview of user accounts and ability to modify and view service permissions.',
                    ],
                    '/adminv2/reports/activity/all' => [
                        'name'        => 'Activity Report',
                        'visible'     => true,
                        'description' => 'Access recent CMS actions like delete, create and modify of files, folders and links.',
                    ],
                    '/adminv2/user/bulk/modify' => [
                        'name'        => 'Bulk Modify',
                        'visible'     => true,
                        'description' => 'Make changes to user accounts on bulk by searching on various account attributes.',
                    ],
                    '/adminv2/lists/campus' => [
                        'name'        => 'Campuses',
                        'visible'     => true,
                        'description' => 'Setup the campus within the school and configure which timetables are used by the campus.',
                    ],
                    '/adminv2/user/create' => [
                        'name'        => 'Create User',
                        'visible'     => true,
                        'description' => 'Setup a new user account for use in the platform.',
                    ],
                    '/adminv2/lists/house' => [
                        'name'        => 'Houses',
                        'visible'     => true,
                        'description' => 'Setup the school pastoral houses.',
                    ],
                    '/adminv2/import/userAccount' => [
                        'name'        => 'Import CSV',
                        'visible'     => true,
                        'description' => 'Make changes to user accounts on bulk by uploading a CSV file.',
                    ],
                    '/adminv2/import/photos/upload' => [
                        'name'        => 'Import Portraits',
                        'visible'     => true,
                        'description' => 'Manually upload portrait photos for users when the SIS does not automatically provide them.',
                    ],
                    '/adminv2/reports/login/all' => [
                        'name'        => 'Login Log',
                        'visible'     => true,
                        'description' => 'View a list of all the recent successful and unsuccessful login attempts to the system.',
                    ],
                    '/adminv2/profile-button' => [
                        'name'        => 'Profile Buttons',
                        'visible'     => true,
                        'description' => 'Add your own custom buttons to a users profile page.',
                    ],
                    '/adminv2/profile-editor' => [
                        'name'        => 'Profile Editor',
                        'visible'     => true,
                        'description' => 'Customise the fields and buttons on the staff, student and parent profile screens.',
                    ],
                    '/adminv2/user/stale' => [
                        'name'        => 'Remove Users',
                        'visible'     => true,
                        'description' => 'View and remove users which have not logged into the platform for a while.',
                    ],
                    '/adminv2/lists/residential-house' => [
                        'name'        => 'Residential Houses',
                        'visible'     => true,
                        'description' => 'Setup the residential boarding houses that students attend.',
                    ],
                    '/adminv2/lists/roles' => [
                        'name'        => 'Roles',
                        'visible'     => true,
                        'description' => 'Configure user roles within the system, the type of access they have and permissions.',
                    ],
                    '/adminv2/shaps' => [
                        'name'        => 'Schoolbox Help',
                        'visible'     => true,
                        'description' => 'Ensure your staff have access to our online training, guides and forums.',
                    ],
                    '/adminv2/lists/user-pronoun' => [
                        'name'        => 'User Pronouns',
                        'visible'     => true,
                        'description' => 'Configure the drop down list of pronouns available for users.',
                    ],
                    '/adminv2/ups' => [
                        'name'        => 'User Provisioning System',
                        'visible'     => true,
                        'description' => 'Configure automatic user account creation and updates from your school database (SIS).',
                        'children'    => [
                            '/adminv2/ups/student' => [
                                'name'    => 'Student',
                                'visible' => true,
                            ],
                            '/adminv2/ups/parent' => [
                                'name'    => 'Parent',
                                'visible' => true,
                            ],
                            '/adminv2/ups/staff' => [
                                'name'    => 'Staff',
                                'visible' => true,
                            ],
                        ],
                    ],
                    '/adminv2/lists/user-title' => [
                        'name'        => 'User Titles',
                        'visible'     => true,
                        'description' => 'Configure the drop down list of titles available for users.',
                    ],
                    '/adminv2/lists/year-levels' => [
                        'name'        => 'Year Levels',
                        'visible'     => true,
                        'description' => 'Configure the year levels and the policy settings related to those year levels.',
                    ],
                ],
            ],
        ];
    }

    /**
     * Render the self-contained subnav as a purely html fragment
     * so you can slam it straight into a phplib sigh
     *
     * @param string      $url
     * @param string|null $title this title will be used on the page, if provided
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function generateHtml(string $url, ?string $title = null): string
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

            if (count($parentData['children'])) {
                foreach ($parentData['children'] as $childUrl => $childData) {
                    if ($childUrl === $url) {
                        $pageCurrent = $parentUrl;
                        $subpageCurrent = $childUrl;
                    }
                }
            }
        }
        $adminSub = $this->generate();
        return $this->twig->render(
            'subnav.twig',
            [
                'config'      => $this->configLoader,
                'adminSubnav' => $adminSub,
                'pageCurrent' => $pageCurrent,
                'subpageCurrent' => $subpageCurrent,
                'titleOverride' => $title,
            ]
        );
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
     * @param string      $url
     * @param string|null $overrideUrl
     * @param string|null $overrideName
     * @return array
     */
    public function generateBreadcrumbs(string $url, ?string $overrideUrl = null, ?string $overrideName = null): array
    {
        $allCrumbs = [
            ['Administration', '/adminv2/home'],
        ];
        foreach ($this->generate() as $parentUrl => $parentData) {
            $allCrumbs = array_merge($allCrumbs, $this->generateBreadcrumbsSub($parentUrl, $parentData, $url, $overrideUrl, $overrideName));
        }
        return $allCrumbs;
    }

    /**
     * @param string      $parentUrl
     * @param array       $parentData
     * @param string      $url
     * @param string|null $overrideUrl
     * @param string|null $overrideName
     * @return array
     */
    private function generateBreadcrumbsSub(string $parentUrl, array $parentData, string $url, ?string $overrideUrl = null, ?string $overrideName = null): array
    {
        $allCrumbs = [];
        $parentHasBreadcrumbLink = $parentData['searchable'] ?? true;
        if ($parentUrl === $url) {
            $allCrumbs[] = [
                $overrideName ?? $parentData['name'],
                $overrideUrl ?? ($parentHasBreadcrumbLink ? $parentUrl : null),
            ];
        }

        if (count($parentData['children'] ?? [])) {
            foreach ($parentData['children'] as $childUrl => $childData) {
                $childCrumb = $this->generateBreadcrumbsSub($childUrl, $childData, $url, $overrideUrl, $overrideName);
                if ($childCrumb) {
                    if ($parentUrl !== $childUrl) {
                        $allCrumbs[] = [$parentData['name'], $parentHasBreadcrumbLink ? $parentUrl : null];
                    }
                    $allCrumbs = array_merge($allCrumbs, $childCrumb);
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
