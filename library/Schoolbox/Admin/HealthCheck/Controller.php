<?php

namespace Schoolbox\Admin\HealthCheck;

use Alaress\Database\Predicate\Builder;
use Alaress\Web\Controller\Base;
use Aura\Router\Generator;
use Component_Config_Componentname;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\MessageFactory;
use Schoolbox\Resource\Textbox;
use Schoolbox\User\Token\Mapper as TokenMapper;

class Controller extends Base
{
    private TaskD $taskDHelper;
    private Jwt $tokenBuilder;

    /**
     * Usage for PST/Support:
     * If you don't provide any additional parameters it will run all audits
     * BASE URL: /adminv2/health-check-export/audit
     *
     * If you need to run a specific audit use the following;
     *      /adminv2/health-check-export/audit?report=uxui
     *      /adminv2/health-check-export/audit?report=uxui,lms,community
     *
     * Current options for this are: assessment, uxui, user, lms, community, sva, implementation, unitplanner, olcstats
     */

    /**
     * Constructor
     *
     * @param Jwt $tokenBuilder
     * @param IConfigLoader $config
     * @param Generator $routeGenerator
     * @param MessageFactory $messageFactory
     * @param Producer $producer
     * @param TokenMapper $tokenMapper
     */
    public function __construct(
        Jwt $tokenBuilder,
        IConfigLoader $config,
        Generator $routeGenerator,
        MessageFactory $messageFactory,
        Producer $producer,
        TokenMapper $tokenMapper
    ) {
        $this->tokenBuilder = $tokenBuilder;

        $this->taskDHelper = new TaskD(
            $this->tokenBuilder,
            $config,
            $routeGenerator,
            $messageFactory,
            $producer,
            $tokenMapper
        );
    }

    /**
     * base endpoint, append list of queries to run
     * this will build the urls, send to task-d to download the csv files
     * upload to storage, and notify the user.
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function getAudit()
    {
        // Reports to be included in "all" reports
        $allReports = ['assessment', 'uxui', 'user', 'lms', 'community'];
        $query = $this->requestQuery->get('report');
        if ('' === trim((string)$query)) {
            //if no value was handed, just assume the user was after everything
            $query = 'all';
        }

        //array of reports to be run
        $reports = explode(',', $query);
        //if query is 'all' lets add all options in
        if ('all' === $query) {
            $reports = $allReports;
        }
        //loop through all reports and build the sources / routes for all of them.
        foreach ($reports as $report) {
            switch ($report) {
                case 'assessment':
                    /**
                     * specific URL for assessment on folder 0
                     */
                    //helper URL object
                    $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();
                    $this->taskDHelper->addSource('assessment', 'assessment', $urlBase->withPath($this->routeGenerator->generate('learning.getAssessmentAudit', ['folderId' => 0])));
                    break;
                case 'uxui':
                    /**
                     * list of reports for UX/UI Engagement
                     */
                    $this->taskDHelper->addSource('uxui', 'topMenuReport');
                    $this->taskDHelper->addSource('uxui', 'sideMenuReport');
                    $this->taskDHelper->addSource('uxui', 'resourceBrowser');
                    $this->taskDHelper->addSource('uxui', 'homepageTemplates');
                    break;
                case 'user':
                    /**
                     * list of reports for User Management Engagement
                     */
                    $this->taskDHelper->addSource('user', 'listOfRoles');
                    $this->taskDHelper->addSource('user', 'listOfCampuses');
                    $this->taskDHelper->addSource('user', 'listOfYearLevels');
                    $this->taskDHelper->addSource('user', 'listOfHouses');
                    $this->taskDHelper->addSource('user', 'servicePermissions');
                    $this->taskDHelper->addSource('user', 'listOfSuperusers');
                    $this->taskDHelper->addSource('user', 'resourceBrowser');
                    break;
                case 'lms':
                    /**
                     * list of reports for LMS Fundamentals Engagement
                     */
                    $this->taskDHelper->addSource('lms', 'gradeGroups');
                    $this->taskDHelper->addSource('lms', 'academicPeriods');
                    $this->taskDHelper->addSource('lms', 'workTypes');
                    $this->taskDHelper->addSource('lms', 'submissionStates');
                    $this->taskDHelper->addSource('lms', 'lmsSettings');
                    $this->taskDHelper->addSource('lms', 'yearLevelPermissions');
                    $this->taskDHelper->addSource('lms', 'homepageTemplates');
                    break;
                case 'community':
                    /**
                     * list of reports for Community Engagement
                     */
                    $this->taskDHelper->addSource('community', 'newsTopics');
                    $this->taskDHelper->addSource('community', 'newsServicePermissions');
                    $this->taskDHelper->addSource('community', 'newsSettings');
                    $this->taskDHelper->addSource('community', 'eventTypes');
                    $this->taskDHelper->addSource('community', 'calendarServicePermissions');
                    $this->taskDHelper->addSource('community', 'parentNewsNotifications');
                    $this->taskDHelper->addSource('community', 'homepageTemplates');
                    break;
                case 'sva':
                    /**
                     * list of reports for sva reports
                     */
                    $this->taskDHelper->addSource('', 'svaMonthlyLogins');
                    $this->taskDHelper->addSource('', 'svaNewsArticleViews');
                    $this->taskDHelper->addSource('', 'svaNewsArticleViewsPerRolePerArticle');
                    $this->taskDHelper->addSource('', 'svaNewsArticleViewsPerMonthBySKC');
                    $this->taskDHelper->addSource('', 'svaNewsArticleViewsPerMonthByActiveAlumni');
                    $this->taskDHelper->addSource('', 'svaNewsCreatedBySVATeam');
                    $this->taskDHelper->addSource('', 'svaHomepageViewsByKSC');
                    $this->taskDHelper->addSource('', 'svaHomepageViewsByAllPerRolePerMonth');
                    $this->taskDHelper->addSource('', 'svaHomepageViewsForCommunityForumsPage');
                    $this->taskDHelper->addSource('', 'svaHomepageViewsForOpportunitiesPage');
                    $this->taskDHelper->addSource('', 'svaHomepageViewsForProfessionalReadingPage');
                    $this->taskDHelper->addSource('', 'svaHomepageViewsForEvaluationPage');
                    $this->taskDHelper->addSource('', 'svaHomepageViewsForActiveAlumniPage');
                    $this->taskDHelper->addSource('', 'svaHomepageViewsForRrrHubSchoolsPage');
                    $this->taskDHelper->addSource('', 'svaForumThreadsViews');
                    $this->taskDHelper->addSource('', 'svaForumRepliesViews');
                    break;
                case 'implementation':
                    /**
                     * list of reports for Implementation Engagement
                     */
                    $this->taskDHelper->addSource('', 'implementationServicePermissions');
                    $this->taskDHelper->addSource('', 'implementationRoleList');
                    $this->taskDHelper->addSource('', 'implementationCampusList');
                    $this->taskDHelper->addSource('', 'implementationHouseList');
                    $this->taskDHelper->addSource('', 'implementationYearLevels');
                    $this->taskDHelper->addSource('', 'implementationNewsTopics');
                    $this->taskDHelper->addSource('', 'implementationCalendarEventTypes');
                    $this->taskDHelper->addSource('', 'implementationNotificationSettings');
                    // TODO: $this->taskDHelper->addSource('', 'implementationTopMenuReport');
                    $this->taskDHelper->addSource('', 'implementationSideMenuReport');
                    $this->taskDHelper->addSource('', 'implementationAcademicPeriods');
                    break;
                case 'unitplanner':
                    /**
                     * list of reports for Unit Planner Engagement
                     */
                    $this->taskDHelper->addSource('', 'unitplannerFrameworks');
                    $this->taskDHelper->addSource('', 'unitplannerCourseTextboxes');
                    $this->taskDHelper->addSource('', 'unitplannerUnitTextboxes');
                    $this->taskDHelper->addSource('', 'unitplannerComponents');
                    $this->taskDHelper->addSource('', 'unitplannerAssessments');
                    break;
                case 'olcstats':
                    /**
                     * list of reports for OLC Reporting Stats
                     */
                    $this->taskDHelper->addSource('', 'olcstatsQuizAttempts');
                    $this->taskDHelper->addSource('', 'olcstatsTaskCompletions');
                    $this->taskDHelper->addSource('', 'olcstatsDueWorkSubmissions');
                    break;
                default:
                    throw new SchoolboxException("Report '{$report}' Does not exist.");
            }
        }

        //after looping through all reports, send them all to here...
        $this->taskDHelper->sendToTaskD('HealthCheck', $this->getUser());

        //redirect home
        $this->response = $this->redirectFound('/');
    }

    /**
     * URL for getting the list of homepage templates, and returns a csv object
     */
    public function homepageTemplates()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("SELECT
                name AS 'Homepage Templates'
            FROM
                homepageTemplate
            ORDER BY
                name ASC; ");
        $statement->execute();

        (new Helper())->queryToCSV($statement, 'HomepageTemplates.csv');
    }

    /**
     * UXUI
     * Top Menu Report.csv
     */
    public function topMenuReport()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("SELECT
            mt.text as 'Icon Name',
            mt.title as 'Tooltip',
            mt.url as 'Destination URL',
            (SELECT
                group_concat(c.name)
            FROM
                `luCampus` c
            JOIN
                menu_top_campus mtc on mtc.menu_top_id = mt.id AND mtc.campus_id = c.id
            WHERE
                c.deleted_at IS NULL
            ) AS 'Campus',
            (SELECT
                group_concat(g.name)
            FROM `groups` g
            JOIN
                menu_top_role mtr ON mtr.menu_top_id = mt.id AND mtr.role_id = g.id
            WHERE
                g.deleted_at IS NULL
            ) as 'Role'
        FROM
            menu_top mt
        WHERE
            mt.deleted_at is NULL
        ORDER BY
            sort_order asc; ");
        $statement->execute();

        (new Helper())->queryToCSV($statement, 'TopMenuReport.csv');
    }

    /**
     * UXUI
     * Side Menu Report .csv
     */
    public function sideMenuReport()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            ms.title as 'Side Menu Item',
            ms.url as 'URL',
            (SELECT
                group_concat(c.name)
            FROM
                `luCampus` c
            JOIN
                menu_side_campus msc on msc.menu_side_id = ms.id
            WHERE
                msc.campus_id = c.id
            AND
                c.deleted_at IS NULL
            ) AS 'Campus',
            (SELECT
                group_concat(g.name)
            FROM
                `groups` g
            JOIN
                menu_side_role msr on msr.menu_side_id = ms.id
            WHERE
                msr.role_id = g.id
            AND
                g.deleted_at IS NULL
            ) AS 'Role'
        FROM
            menu_side ms
        WHERE
            ms.deleted_at is NULL
        GROUP BY
            ms.title,ms.url
        ORDER BY
            ms.title,'Campus';
            ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'SideMenuReport.csv');
    }

    /**
     * UXUI
     * resourceBrowser .csv
     */
    public function resourceBrowser()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            f.name AS 'Folder/Page Name',
            f.id AS  'Folder/Page ID',
            IF(hc.template, 'homepage', 'folder') AS 'Page Status',
            (SELECT
                  IF(group_concat(g.name) IS NULL, '', group_concat(g.name))
              FROM
                 `groups` g
                     join
                 perms p on g.id = p.gid
              WHERE
                     p.type = 1
               AND
                 g.deleted_at is null
               AND f.id = p.cid
            ) as 'Read',
            (SELECT
                 IF(group_concat(g.name) IS NULL, '', group_concat(g.name))
              FROM
                 `groups` g
              JOIN
                 perms p on g.id = p.gid
              WHERE
                     p.type = 2
              AND
                 g.deleted_at is null
              AND f.id = p.cid
            ) AS 'Write'
        FROM
            folder f
        LEFT JOIN
            homepage_config hc ON f.id = hc.cid
        WHERE
                f.parent = 0
          AND
            f.deleted_at IS NULL;
            ");
        $statement->execute();

        (new Helper())->queryToCSV($statement, 'ResourceBrowser.csv');
    }

    /**
     * USER
     * listOfRoles.csv
     */
    public function listOfRoles()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            g.NAME   AS 'Role Name',
            Count(*) AS 'User Count'
        FROM
            `groups` g
        JOIN
            user u ON g.id = u.group_id
        WHERE
            u.deleted_at IS NULL
        AND
            g.deleted_at IS NULL
        GROUP BY
            g.NAME;
        ");
        $statement->execute();

        (new Helper())->queryToCSV($statement, 'listOfRoles.csv');
    }

    /**
     * USER
     * listOfCampuses.csv
     */
    public function listOfCampuses()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            c.NAME   AS 'Campus Name',
            Count(*) AS 'User Count'
        FROM
            luCampus c
        JOIN
            userCampus uc ON c.id = uc.campus
        JOIN
            user u ON uc.uid = u.id
        WHERE
            u.deleted_at IS NULL
        GROUP BY
            c.NAME;
        ");
        $statement->execute();

        (new Helper())->queryToCSV($statement, 'listOfCampuses.csv');
    }

    /**
     * USER
     * listOfYearLevels.csv
     */
    public function listOfYearLevels()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            lyl.NAME AS 'Year Level',
            Count(*) AS 'User Count'
        FROM
            lu_year_level lyl
        JOIN
            user u ON lyl.id = u.year_id
        WHERE
            lyl.deleted_at IS NULL
        AND
            u.deleted_at IS NULL
        GROUP BY
            lyl.NAME;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'listOfYearLevels.csv');
    }

    /**
     * USER
     * listOfHouses.csv
     */
    public function listOfHouses()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            h.house AS 'House Level',
            Count(*) AS 'User Count'
        FROM
            house h
        JOIN
            user u ON h.id = u.house
        WHERE
            h.deleted_at IS NULL
        AND
            u.deleted_at IS NULL
        GROUP BY
            h.house;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'listOfHouses.csv');
    }

    /**
     * USER
     * servicePermissions.csv
     */
    public function servicePermissions()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            ls.NAME AS 'Service Type',
            CASE
                WHEN ua.service = '2' THEN lc.NAME
                WHEN ua.service = '6' THEN h.NAME
                ELSE 'N/A'
            END AS 'Service',
            CASE type
                WHEN '3' THEN 'Moderator'
                WHEN '2' THEN 'Write'
                WHEN '1' THEN 'Read'
            END AS 'Permission',
            Concat(u.firstname,' ',u.lastname,' (',u.id,')') AS 'User'
        FROM
            userAccess ua
        LEFT JOIN
            lu_service ls ON ua.service = ls.id
        LEFT JOIN
            user u ON ua.uid = u.id
        LEFT JOIN
            luCampus lc ON ua.sid = lc.id
        LEFT JOIN
            luResidentialHouse h ON ua.sid = h.id
        WHERE
            NOT service = '1'
        AND
            u.deleted_at IS NULL
        ORDER BY
            ua.service,ua.type,ua.sid,u.id;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'servicePermissions.csv');
    }

    /**
     * USER
     * listOfSuperusers.csv
     */
    public function listOfSuperusers()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            concat(u.firstname,' ',u.lastname,' (',u.id,')') AS 'Superuser'
        FROM
            superusers s
        JOIN
            user u ON s.id = u.id
        WHERE
            u.deleted_at IS NULL;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'listOfSuperusers.csv');
    }

    /**
     * LMS
     * gradeGroups.csv
     */
    public function gradeGroups()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            sgg.name as 'Grade Group',
            sg.lower_bound as 'Lower Bound',
            sg.raw_grade as 'Normalised Result',
            sg.caption as 'Caption'
        FROM
            submission_grade sg
        LEFT JOIN
            submission_grade_group sgg ON sg.submission_grade_group_id = sgg.id;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'gradeGroups.csv');
    }

    /**
     * LMS
     * academicPeriods.csv
     */
    public function academicPeriods()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            title as 'Academic Period Name',
            start as 'Start Date',
            display_finish 'End Date',
            IF(archived, 'Yes', 'No') as 'Archived?'
        FROM
            term;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'academicPeriods.csv');
    }

    /**
     * LMS
     * workTypes.csv
     */
    public function workTypes()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            NAME as 'Work Type',
            IF(show_on_grades_page, 'Yes', 'No') as 'Display on Grades Page',
            CASE CONCAT(IF(is_visible_to_students, 1, 0), '-', IF(is_visible_to_parents, 1, 0))
                WHEN '0-0' THEN 'staffOnly'
                WHEN '0-1' THEN 'staffAndParents'
                WHEN '1-0' THEN 'staffAndStudents'
                WHEN '1-1' THEN 'staffAndStudentsAndParents'
            END AS 'Visible'
        FROM
            lu_work_type
        WHERE
            deleted_at IS NULL
        ORDER BY
            sequence ASC;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'workTypes.csv');
    }

    /**
     * LMS
     * submissionStates.csv
     */
    public function submissionStates()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            code AS 'Code',
            IF(display_as = NULL, 'Empty', display_as) AS 'Display Name',
            normalise_as AS 'Normalised Mark'
        FROM
            submission_errors
        WHERE
            deleted_at IS NULL
        ORDER BY
            sequence ASC;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'submissionStates.csv');
    }

    /**
     * LMS
     * lmsSettings.csv
     */
    public function lmsSettings()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            title AS 'LMS Policy',
                IF(c.name = 'submission_access',
                    CASE
                        WHEN value = '0' then 'Staff Only'
                        WHEN value = '1' then 'Staff & Students'
                        WHEN value = '2' then 'Staff, Students their Parents'
                    END,
                IF(c.name = 'submission_visibility_max',
                    CASE
                        WHEN value = '0' then 'Staff Only'
                        WHEN value = '1' then 'Staff & Students'
                        WHEN value = '2' then 'Staff, Students their Parents'
                    END,
                IF(c.name = 'overall_grade',
                    CASE
                        WHEN value = '-2' then 'Percentage'
                        WHEN value = '-1' then 'None'
                        ELSE sgg.name
                    END,
                IF(c.name = 'default_grade_group', sgg.name,
                IF(c.name = 'lms_resubmit_window',value,
                IF(c.name = 'lms_duetime_default',value,
                IF(value, 'ON', 'OFF'))))))) AS 'Option Selection'
        FROM
            config c
        LEFT JOIN
            submission_grade_group sgg on c.value = sgg.id
        WHERE
            c.hidden = 'false'
        AND
            c.name IN ('curriculum_tagging_enabled',
                     'submission_access',
                     'restrict_student_submissions',
                     'show_overall_grade',
                     'similarity_check_student_visible',
                     'submission_access',
                     'restrict_parent_duework_access',
                     'display_grade_graph',
                     'restrict_parent_grades',
                     'enable_grades_criteria',
                     'grade_comments_enable',
                     'default_grade_group',
                     'rubric_show_points',
                     'similarity_check_allow_students_report_access',
                     'allow_student_class_access',
                     'destructive_activity_export_enabled',
                     'custom_assessment_participation',
                     'self_assessment_enabled',
                     'course_markbook_enable',
                     'assessment_calendar_enabled',
                     'grade_gradient_enable',
                     'class_markbook_enable',
                     'rubric_markbook_enable',
                     'grade_capability_display',
                     'grade_progression_display',
                     'lms_permit_staff_feedback_delete',
                     'overall_grade',
                     'lms_cohort_comparison',
                     'view_rank',
                     'lms_resubmit_window',
                     'lms_duetime_default',
                     'submission_visibility_max'

        )
        ORDER BY
            c.sequence;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'lmsSettings.csv');
    }

    /**
     * LMS
     * yearLevelPermissions.csv
     */
    public function yearLevelPermissions()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
               name                                       AS 'Name',
                CASE overall_grade
                    WHEN '1' THEN 'Standard'
                    WHEN '-1' THEN 'None'
                    WHEN '-2' THEN 'Percentage'
                ELSE 'Not Selected'
               END                                        AS 'Overall Grade',
               if(show_overall_grade, 'Yes', 'No')        AS 'Show Overall Grade',
               if(display_grade_page, 'Yes', 'No')        AS 'Display Grade Page',
               if(display_assessment_grade, 'Yes', 'No')  AS 'Display Assessment Grade',
               if(display_rubric_grade, 'Yes', 'No')      AS 'Display Rubric Grade',
               if(display_capability_grade, 'Yes', 'No')  AS 'Display Capability Grade',
               if(display_progression_grade, 'Yes', 'No') AS 'Display Progression Grade',
               if(show_rubric_points, 'Yes', 'No')        AS 'Show Rubric Points',
               if(lms_cohort_comparison, 'Yes', 'No')     AS 'Display Cohort Comparison',
               if(learning_moments_enabled, 'Yes', 'No')  AS 'Learning Moments Enabled'
        FROM
            lu_year_level
        WHERE
            deleted_at IS NULL
        ORDER
            BY sequence ASC;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'yearLevelPermissions.csv');
    }

    /**
     * COMMUNITY
     * newsTopics.csv
     */
    public function newsTopics()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            NAME AS 'Topic Name',
            slug AS 'Topic Tag'
        FROM
            newsTopic
        WHERE
            deleted_at IS NULL
        ORDER BY
            sequence;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'newsTopics.csv');
    }

    /**
     * COMMUNITY
     * newsServicePermissions.csv
     */
    public function newsServicePermissions()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            Concat(u.firstname,' ', u.lastname, '(', u.id, ')') AS 'User',
            CASE type
              WHEN '3' THEN 'Moderator'
              WHEN '2' THEN 'Write'
              WHEN '1' THEN 'Read'
              ELSE 'Other'
            END AS 'News Permission'
        FROM
          userAccess ua
        JOIN
          user u ON ua.uid = u.id
        WHERE
          service = '3';
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'newsServicePermissions.csv');
    }

    /**
     * COMMUNITY
     * newsSettings.csv
     */
    public function newsSettings()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            title AS 'Option',
            IF(value = 'list', 'List-Left',
            IF(value = 'list-reversed', 'List-Right',
            IF(value = '1', 'Enabled',
            IF(value = '0', 'Disabled', value
            )))) AS 'Choice'
        FROM
            config
        WHERE
            name IN (
            'news_staff_post_enabled',
            'global_moderation',
            'comms_comments_enabled',
            'news_default_expiry',
            'read_more',
            'save_for_later',
            'comms_news_article_list_layout',
            'comms_news_article_list_fallback_image')
        ORDER BY
          sequence;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'newsSettings.csv');
    }

    /**
     * COMMUNITY
     * eventTypes.csv
     */
    public function eventTypes()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
                eventTypeID as 'ID',
                eventType as 'Event',
                eventColour as 'Event Colour'
        FROM
                luEventType
        WHERE
                deleted_at is NULL
        ORDER BY
                sequence ASC;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'eventTypes.csv');
    }

    /**
     * COMMUNITY
     * calendarServicePermissions.csv
     */
    public function calendarServicePermissions()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
                  concat(u.firstname,' ', u.lastname, ' (', u.id, ')') AS 'User',
                  lc.name as 'Campus',
                  CASE type
                      WHEN '3' THEN 'Moderator'
                      WHEN '2' THEN 'Write'
                      WHEN '1' THEN 'Read'
                    ELSE 'Other'
                  END as 'Permission'
        FROM
                  userAccess ua
        LEFT JOIN
                  user u on ua.uid = u.id
        LEFT JOIN
                  luCampus lc on ua.sid = lc.id
        WHERE
                  u.deleted_at is NULL
        AND
                  ua.service = '2'
        ORDER BY
                  ua.uid;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'calendarServicePermissions.csv');
    }

    /**
     * COMMUNITY
     * parentNewsNotifications.csv
     */
    public function parentNewsNotifications()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            g.name as 'Parent Role',
            ms.description as 'Notification',
            mrp.mask as 'Allowed',
            mrp.role_default as 'Defaults'
        FROM
            msg_route_perm mrp
        LEFT JOIN
            `groups` g on g.id = mrp.role_id
        JOIN
            msg_source ms on ms.id = mrp.msg_source_id
        WHERE
            g.deleted_at is NULL
        AND
            g.group_type = 'parent'
        ORDER BY
            g.id;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'parentNewsNotifications.csv');
    }

    // ===============================================================================================
    // ==================================== START SVA REPORTS ========================================
    // ===============================================================================================

    /**
     * SVA
     * svaMonthlyLogins.csv
     */
    public function svaMonthlyLogins()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
          ll.username as 'Username',
          g.name as 'Role',
          concat((MONTHNAME(ll.date)),' ',(YEAR(ll.date))) AS Month,
          count(*) as 'Login Count'
        FROM
          login_log ll
        JOIN
          user u on ll.username = u.username
        JOIN
          groups g on u.group_id = g.id and not g.id in (2,9)
        GROUP BY
          username,month
        ORDER BY
          ll.username,month;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaMonthlyLogins.csv');
    }

    /**
     * SVA
     * svaNewsArticleViews.csv
     */
    public function svaNewsArticleViews()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            cnas.article_id as 'News ID',
            cna.title as 'News Title',
            count(*) as 'Total Views',
            cna.publish_at as 'Publish Date'
        FROM
            comms_news_article_statistics cnas
        LEFT JOIN
            comms_news_article cna on cnas.article_id = cna.id
        WHERE
            cnas.deleted_at is NULL
        group by
            article_id;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaNewsArticleViews.csv');
    }
    /**
     * SVA
     * svaNewsArticleViewsPerRolePerArticle.csv
     */
    public function svaNewsArticleViewsPerRolePerArticle()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            article_id as 'News ID',
            cna.title as 'News Title',
            coalesce((SELECT
            group_concat(nt.name)
        FROM
            newsTopic nt
        JOIN
            comms_news_article_topic cnat on nt.newsTopicID = cnat.topic_id
        WHERE
            cnat.article_id = cna.id),'No Topic Listed') as 'Topics',
            g.name as 'Role',
            count(*) as 'Total Views',
            cna.publish_at as 'Publish Date'
        FROM
            comms_news_article_statistics cnas
        JOIN
            groups g on cnas.role_id = g.id and not g.id in (2,9)
        JOIN
            comms_news_article cna on cnas.article_id = cna.id
        GROUP BY
            article_id,role_id;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaNewsArticleViewsPerRolePerArticle.csv');
    }
    /**
     * SVA
     * svaNewsArticleViewsPerMonthBySKC.csv
     */
    public function svaNewsArticleViewsPerMonthBySKC()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            article_id as 'News ID',
            cna.title as 'News Title',
            coalesce((SELECT
            group_concat(nt.name)
        FROM
            newsTopic nt
        JOIN
            comms_news_article_topic cnat on nt.newsTopicID = cnat.topic_id
        WHERE
            cnat.article_id = cna.id),'No Topic Listed') as 'Topics',
            concat((MONTHNAME(cnas.created_at)),' ',(YEAR(cnas.created_at))) AS Month,
            count(DISTINCT user_id) as 'View Count',
            cna.publish_at as 'Publish Date'
        FROM
            comms_news_article_statistics cnas
        JOIN
            user u on cnas.user_id = u.id
        JOIN
            comms_news_article cna on cnas.article_id = cna.id
        WHERE
            u.group_id = '5'
        GROUP BY
            cnas.article_id, month;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaNewsArticleViewsPerMonthBySKC.csv');
    }
    /**
     * SVA
     * svaNewsArticleViewsPerMonthByActiveAlumni.csv
     */
    public function svaNewsArticleViewsPerMonthByActiveAlumni()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            article_id as 'News ID',
            cna.title as 'News Title',
            coalesce((SELECT
            group_concat(nt.name)
        FROM
            newsTopic nt
        JOIN
            comms_news_article_topic cnat on nt.newsTopicID = cnat.topic_id
        WHERE
            cnat.article_id = cna.id),'No Topic Listed') as 'Topics',
            concat((MONTHNAME(cnas.created_at)),' ',(YEAR(cnas.created_at))) AS Month,
            count(DISTINCT user_id) as 'View Count',
            cna.publish_at as 'Publish Date'
        FROM
            comms_news_article_statistics cnas
        JOIN
            user u on cnas.user_id = u.id
        JOIN
            comms_news_article cna on cnas.article_id = cna.id
        WHERE
            u.group_id = '12'
        group by
            cnas.article_id, month;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaNewsArticleViewsPerMonthByActiveAlumni.csv');
    }
    // ===============================================================================================
    // =================== TO DO: ADD 'News Article Views (Specific)' Here ===========================
    // ===============================================================================================
    /**
     * SVA
     * svaNewsCreatedBySVATeam.csv
     */
    public function svaNewsCreatedBySVATeam()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            concat((MONTHNAME(cna.created_at)),' ',(YEAR(cna.created_at))) AS Month,
            concat(u.firstname,' ',u.lastname,' (',u.id,')') as 'Author',
            count(*) as 'Create Count'
        FROM
            comms_news_article cna
        JOIN
            user u on cna.author_id = u.id
        WHERE
            author_id in (4,13,111,110,5,6)
        GROUP BY
            author_id,month
        ORDER BY
            cna.created_at;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaNewsCreatedBySVATeam.csv');
    }
    /**
     * SVA
     * svaHomepageViewsByKSC.csv
     */
    public function svaHomepageViewsByKSC()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))) AS Month,
            f.name as 'Homepage',
            g.name as 'Role',
            SUM(hv.hitcount) as 'Sum Hitcount'
        FROM
            homepage_visits hv
        JOIN
            groups g on hv.user_group_id = g.id and not g.id in (2,9)
        JOIN
            folder f on hv.category_id = f.id
        WHERE
            hv.user_group_id = '5'
        GROUP BY
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))),hv.user_group_id,hv.category_id
        ORDER BY
            hv.date,hv.category_id,hv.user_group_id;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaHomepageViewsByKSC.csv');
    }
    /**
     * SVA
     * svaHomepageViewsByAllPerRolePerMonth.csv
     */
    public function svaHomepageViewsByAllPerRolePerMonth()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))) AS Month,
            f.name as 'Homepage',
            g.name as 'Role',
            SUM(hv.hitcount) as 'Sum Hitcount'
        FROM
            homepage_visits hv
        JOIN
            groups g on hv.user_group_id = g.id and not g.id in (2,9)
        JOIN
            folder f on hv.category_id = f.id
        GROUP BY
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))),hv.user_group_id,hv.category_id
        ORDER BY
            hv.date,hv.category_id,hv.user_group_id;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaHomepageViewsByAllPerRolePerMonth.csv');
    }
    // ===============================================================================================
    // ====================== TO DO: ADD 'Homepage views per user' Here ==============================
    // ===============================================================================================
    /**
     * SVA
     * svaHomepageViewsForCommunityForumsPage.csv
     */
    public function svaHomepageViewsForCommunityForumsPage()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))) AS Month,
            f.name as 'Homepage',
            g.name as 'Role',
            SUM(hv.hitcount) as 'Sum Hitcount'
        FROM
            homepage_visits hv
        JOIN
            groups g on hv.user_group_id = g.id and not g.id in (2,9)
        JOIN
            folder f on hv.category_id = f.id
        WHERE
            hv.category_id = '831'
        GROUP BY
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))),hv.user_group_id,hv.category_id
        ORDER BY
            hv.date,hv.category_id,hv.user_group_id;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaHomepageViewsForCommunityForumsPage.csv');
    }
    /**
     * SVA
     * svaHomepageViewsForOpportunitiesPage.csv
     */
    public function svaHomepageViewsForOpportunitiesPage()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))) AS Month,
            f.name as 'Homepage',
            g.name as 'Role',
            SUM(hv.hitcount) as 'Sum Hitcount'
        FROM
            homepage_visits hv
        JOIN
            groups g on hv.user_group_id = g.id and not g.id in (2,9)
        JOIN
            folder f on hv.category_id = f.id
        WHERE
            hv.category_id = '18'
        GROUP BY
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))),hv.user_group_id,hv.category_id
        ORDER BY
            hv.date,hv.category_id,hv.user_group_id;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaHomepageViewsForOpportunitiesPage.csv');
    }
    /**
     * SVA
     * svaHomepageViewsForProfessionalReadingPage.csv
     */
    public function svaHomepageViewsForProfessionalReadingPage()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            g.name as 'Role',
            concat(f.name,' (',f.id,')') as 'Homepage',
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))) AS Month,
            SUM(hv.hitcount) as 'Sum Hitcount'
        FROM
            homepage_visits hv
        JOIN
            groups g on hv.user_group_id = g.id and not g.id in (2,9)
        JOIN
            folder f on hv.category_id = f.id
        WHERE
            (
            f.id = 676
            OR
            f.parent = 676
            )
        GROUP BY
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))),hv.user_group_id,hv.category_id
        ORDER BY
            hv.user_group_id,hv.category_id,hv.date;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaHomepageViewsForProfessionalReadingPage.csv');
    }
    /**
     * SVA
     * svaHomepageViewsForEvaluationPage.csv
     */
    public function svaHomepageViewsForEvaluationPage()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))) AS Month,
            f.name as 'Homepage',
            g.name as 'Role',
            SUM(hv.hitcount) as 'Sum Hitcount'
        FROM
            homepage_visits hv
        JOIN
            groups g on hv.user_group_id = g.id and not g.id in (2,9)
        JOIN
            folder f on hv.category_id = f.id
        WHERE
            hv.category_id = '14'
        GROUP BY
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))),hv.user_group_id,hv.category_id
        ORDER BY
            hv.date,hv.category_id,hv.user_group_id;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaHomepageViewsForEvaluationPage.csv');
    }
    /**
     * SVA
     * svaHomepageViewsForActiveAlumniPage.csv
     */
    public function svaHomepageViewsForActiveAlumniPage()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            g.name as 'Role',
            concat(f.name,' (',f.id,')') as 'Homepage',
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))) AS Month,
            SUM(hv.hitcount) as 'Sum Hitcount'
        FROM
            homepage_visits hv
        JOIN
            groups g on hv.user_group_id = g.id and not g.id in (2,9)
        JOIN
            folder f on hv.category_id = f.id
        WHERE
            (
            f.id = 382
            OR
            f.parent = 382
            )
        GROUP BY
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))),hv.user_group_id,hv.category_id
        ORDER BY
            hv.user_group_id,hv.category_id,hv.date;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaHomepageViewsForActiveAlumniPage.csv');
    }
    /**
     * SVA
     * svaHomepageViewsForRrrHubSchoolsPage.csv
     */
    public function svaHomepageViewsForRrrHubSchoolsPage()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            g.name as 'Role',
            concat(f.name,' (',f.id,')') as 'Homepage',
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))) AS Month,
            SUM(hv.hitcount) as 'Sum Hitcount'
        FROM
            homepage_visits hv
        JOIN
            groups g on hv.user_group_id = g.id and not g.id in (2,9)
        JOIN
            folder f on hv.category_id = f.id
        WHERE
            (
            f.id = 383
            OR
            f.parent = 383
            )
        GROUP BY
            concat((MONTHNAME(hv.date)),' ',(YEAR(hv.date))),hv.user_group_id,hv.category_id
        ORDER BY
            hv.user_group_id,hv.category_id,hv.date;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaHomepageViewsForRrrHubSchoolsPage.csv');
    }
    /**
     * SVA
     * svaForumThreadsViews.csv
     */
    public function svaForumThreadsViews()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            concat(f.name,' (',f.id,')') as 'Homepage',
            concat((MONTHNAME(ft.date)),' ',(YEAR(ft.date))) AS Month,
            count(*) as 'Forum Thread Count'
        FROM
            forumThread ft
        JOIN
            folder f on ft.categoryID = f.id
        GROUP BY
            f.name,month(ft.date)
        ORDER BY
            f.name,ft.date;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaForumThreadsViews.csv');
    }
    /**
     * SVA
     * svaForumReplies.csv
     */
    public function svaForumRepliesViews()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            ft.threadID as 'Thread ID',
            ft.title as 'Thread Name',
            concat((MONTHNAME(fp.date)),' ',(YEAR(fp.date))) AS Month,
            count(*) as 'Forum Reply Count'
        FROM
            forumPost fp
        JOIN
            forumThread ft on fp.threadID = ft.threadID
        GROUP BY
            ft.title,month(fp.date)
        ORDER BY
            ft.threadID,ft.date;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'svaForumReplies.csv');
    }
    // ===============================================================================================
    // ============= TO DO: ADD 'Social Stream Posts (per month, per page)' Here =====================
    // ===============================================================================================

    // ===============================================================================================
    // ==================================== END SVA REPORTS ==========================================
    // ===============================================================================================

    // ===============================================================================================
    // ============================== START IMPLEMENTATION REPORTS ===================================
    // ===============================================================================================

    /**
     * IMPLEMENTATION
     * implementationServicePermissions.csv
     */
    public function implementationServicePermissions()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            ls.NAME AS 'Service Type',
            group_concat(concat(u.firstname,' ',u.lastname,' (',u.id,')')) AS 'Users'
        FROM
            lu_service ls
        LEFT JOIN
            userAccess ua on ls.id = ua.service
        LEFT JOIN
            user u on ua.uid = u.id and u.deleted_at is NULL
        WHERE
            NOT ls.name = 'Homepage'
        GROUP BY
            ls.name
        UNION
        SELECT
            'Superusers' as 'Service Type',
            group_concat(concat(u.firstname,' ',u.lastname,' (',u.id,')')) AS 'Users'
        FROM
            superusers s
        LEFT JOIN
            user u ON s.id = u.id
        WHERE
            u.deleted_at IS NULL;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'implementationServicePermissions.csv');
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * IMPLEMENTATION
     * implementationRoleList.csv
     */
    public function implementationRoleList()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            g.NAME   AS 'Role Name',
            Count(u.id) AS 'User Count'
        FROM
            groups g
        LEFT JOIN
            user u ON g.id = u.group_id
        WHERE
            u.deleted_at IS NULL
        AND
            g.deleted_at IS NULL
        GROUP BY
            g.NAME;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'implementationRoleList.csv');
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * IMPLEMENTATION
     * implementationCampusList.csv
     */
    public function implementationCampusList()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            c.NAME   AS 'Campus Name',
            Count(u.id) AS 'User Count'
        FROM
            luCampus c
        JOIN
            userCampus uc ON c.id = uc.campus
        JOIN
            user u ON uc.uid = u.id
        WHERE
            u.deleted_at IS NULL
        GROUP BY
            c.NAME;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'implementationCampusList.csv');
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * IMPLEMENTATION
     * implementationHouseList.csv
     */
    public function implementationHouseList()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            h.house AS 'House Level',
            Count(u.id) AS 'User Count'
        FROM
            house h
        LEFT JOIN
            user u ON h.id = u.house
        WHERE
            h.deleted_at IS NULL
        AND
            u.deleted_at IS NULL
        GROUP BY
            h.house;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'implementationHouseList.csv');
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * IMPLEMENTATION
     * implementationYearLevels.csv
     */
    public function implementationYearLevels()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            lyl.NAME AS 'Year Level',
            Count(u.id) AS 'User Count'
        FROM
            lu_year_level lyl
        LEFT JOIN
            user u ON lyl.id = u.year_id
        WHERE
            lyl.deleted_at IS NULL
        AND
            u.deleted_at IS NULL
        GROUP BY
            lyl.NAME;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'implementationYearLevels.csv');
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * IMPLEMENTATION
     * implementationNewsTopics.csv
     */
    public function implementationNewsTopics()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            NAME AS 'Topic Name'
        FROM
            newsTopic
        WHERE
            deleted_at IS NULL
        ORDER BY
            sequence;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'implementationNewsTopics.csv');
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * IMPLEMENTATION
     * implementationCalendarEventTypes.csv
     */
    public function implementationCalendarEventTypes()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
                eventTypeID as 'ID',
                eventType as 'Event',
                eventColour as 'Event Colour'
        FROM
                luEventType
        WHERE
                deleted_at is NULL
        ORDER BY
                sequence ASC;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'implementationCalendarEventTypes.csv');
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * IMPLEMENTATION
     * implementationNotificationSettings.csv
     */
    public function implementationNotificationSettings()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            g.name as 'Role',
            role_type as 'Role Type',
            ms.description as 'Notification',
            mrp.mask as 'Allowed',
            mrp.role_default as 'Defaults'
        FROM
            msg_route_perm mrp
        LEFT JOIN
            groups g on g.id = mrp.role_id
        LEFT JOIN
            msg_source ms on ms.id = mrp.msg_source_id
        WHERE
            g.deleted_at is NULL
        AND
            mrp.msg_source_id in (34,35,36,37,38,39,40,41,42,49,50,51,52,53)
        ORDER BY
            ms.description,g.id;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'implementationNotificationSettings.csv');
    }
    /**
     * IMPLEMENTATION
     * implementationSideMenuReport.csv
     */
    public function implementationSideMenuReport()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
          mss.section as 'Real Name',
          IF(mss.title != '', mss.title,
            CASE
                WHEN mss.section = 'menu' THEN (select value from config where name = 'title')
                WHEN mss.section = 'subjects' THEN 'My Subjects'
                WHEN mss.section = 'children' THEN 'My Students'
                WHEN mss.section = 'groups' THEN 'My Favorite Groups'
                WHEN mss.section = 'bookmarks' THEN 'My Links'
            END
          ) as 'Label Name',
            CASE
                WHEN mss.visible = '0' THEN 'Hidden'
                WHEN mss.visible = '1' THEN 'Visible'
                ELSE 'N/A'
            END AS 'Visibility'
        FROM
          menu_side_section mss;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'implementationSideMenuReport.csv');
    }
    /**
     * IMPLEMENTATION
     * implementationAcademicPeriods.csv
     */
    public function implementationAcademicPeriods()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            title as 'Academic Period Name',
            coalesce(lyl.name,'') as 'Year Level?',
            start as 'Start Date',
            display_finish 'End Date',
            IF(archived, 'Yes', 'No') as 'Archived?'
        FROM
            term t
        LEFT JOIN
            term_year_level tyl on t.id = tyl.term_id
        LEFT JOIN
            lu_year_level lyl on tyl.year_level_id = lyl.id;
        ");
        $statement->execute();
        (new Helper())->queryToCSV($statement, 'implementationAcademicPeriods.csv');
    }
    // ===============================================================================================
    // =============================== END IMPLEMENTATION REPORTS ====================================
    // ===============================================================================================

    // ===============================================================================================
    // =============================== START UNIT PLANNER REPORTS ====================================
    // ===============================================================================================

    /**
     * UNIT PLANNER
     * unitplannerFrameworks.csv
     */
    public function unitplannerFrameworks()
    {
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();

        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            cs.id AS 'Course ID',
            fcs.name AS 'Course Name',
            cs.subject_code AS 'Subject Code',
            '' AS 'New Subject Code',
            cs.template_id AS 'Framework ID',
            ct.name AS 'Framework',
            '' AS 'New Framework ID',
            '' AS 'New Framework Name',
            CONCAT(:URL,'/course/modify/',cs.id) AS 'Course Link'
        FROM
            course_specification cs
        JOIN
            folder fcs ON cs.folder_id = fcs.id AND fcs.deleted_at IS NULL
        LEFT JOIN
            courseTemplates ct ON cs.template_id = ct.id
        WHERE
            cs.deleted_at IS NULL;
        ");
        $statement->execute(['URL' => $urlBase]);
        (new Helper())->queryToCSV($statement, 'unitplannerFrameworks.csv');
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * UNIT PLANNER
     * unitplannerCourseTextboxes.csv
     * @api   GET /adminv2/health-check-export/audit/unitplannerCourseTextboxes
     * @since 24.1
     */
    public function unitplannerCourseTextboxes()
    {

        $database = FolderMapper::getInstance()->getDatabase();
        $courses = [];

        //build the csv
        $rowHeadings = [
            'Course Title',
            'Subject Code',
            'Course Homepage ID',
            'Course ID',
            'Course Framework',
            'Edit Link',
            'Textbox Component Name',
            'Textbox Component ID',
            'Textbox Title',
            'New Textbox Title',
            'Textbox ID',
            'Textbox Content (empty string, empty state)'
        ];

        // base filename with school code and date
        $filename = sprintf("unit-audit-%s-%s.csv", $this->getConfigLoader()->get('site_school_code'), \Alaress_Date_Format::now()->toIso8601String());
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();

        // fetch all courses
        $courseQuery = $database->prepareQuery("
        SELECT
            courseFolder.name AS 'course.name',
            course_specification.subject_code AS 'course.subject_code',
            courseFolder.id AS 'course.id',
            course_specification.id AS 'course_specification.id',
            courseTemplates.name AS 'framework'
        FROM course_specification
        JOIN courseTemplates ON course_specification.template_id = courseTemplates.id
        JOIN folder courseFolder ON course_specification.folder_id = courseFolder.id
        WHERE course_specification.deleted_at IS NULL
        AND courseFolder.deleted_at IS NULL;
        ");

        $courseQuery->execute();
        while ($row = $courseQuery->fetch(\PDO::FETCH_ASSOC)) {
            $courses[$row['course.id']] = $row;
        }

        $coursePlaceHolder = Builder::getArrayPlaceholders(array_keys($courses), 'courses');

        // fetch all textboxes with components from these courses
        // clone with extension from \Schoolbox\Search\Api\Database\ResultType\Textbox\IndexBuilder::buildUpdates
        $compTextQuery = $database->prepareQuery("SELECT
            tb.id AS id,
            f.id AS folder_id,
            cci_componentname.value AS 'componentName',
            ci.id AS 'componentId',
            tb.heading AS 'textboxHeading',
            tb.updated_at AS date,
            tb.updated_by AS updated_by,
            tb.body AS textboxBody
        FROM component_definition cd
                 JOIN component_instance ci ON ci.component_definition_id = cd.id and ci.module = 'homepage'
                 JOIN folder f ON f.id = ci.identifier
                 JOIN component_config_definition ccd_textbox ON (
            ccd_textbox.component_definition_id = cd.id AND
            ccd_textbox.key = :homepageComponentKey
            )
                 LEFT JOIN component_config_instance cci_textbox ON (
            cci_textbox.component_config_definition_id = ccd_textbox.id AND
            cci_textbox.component_instance_id = ci.id
            )
                 LEFT JOIN textbox tb ON CAST(tb.id AS NCHAR)=cci_textbox.value
                 JOIN component_config_definition ccd_componentname ON (
            ccd_componentname.component_definition_id = cd.id AND
            ccd_componentname.key = :componentConfigComponentNameKey
            )
                 LEFT JOIN component_config_instance cci_componentname ON (
            cci_componentname.component_config_definition_id = ccd_componentname.id AND
            cci_componentname.component_instance_id = ci.id
            )
        WHERE cd.`class` = 'Schoolbox\\\Resource\\\Textbox\\\Component\\\Homepage\\\Controller'
          AND f.deleted_at IS NULL
          AND tb.deleted_at IS NULL
        AND f.id in {$coursePlaceHolder};
        ");


        header('Content-type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");

        $fp = fopen("php://output", 'w');
        fputcsv($fp, $rowHeadings, escape: '');

        /**
         * $rowHeadings = [
         * 'Course Title',
         * 'Subject Code',
         * 'Course Homepage ID',
         * 'Course Specification ID',
         * 'Course Framework',
         * 'Edit Link',
         * 'Textbox Component Name',
         * 'Textbox Component ID',
         * 'Textbox Title',
         * 'New Textbox Title',
         * 'Textbox ID',
         * 'Textbox Content (empty string, empty state)'
         * ];
         */
        $compTextQuery->execute(array_merge(
            [
                'homepageComponentKey' => Textbox\Mapper::HOMEPAGE_COMPONENT_KEY,
                'componentConfigComponentNameKey' => Component_Config_Componentname::KEY,
            ],
            Builder::getArrayParameters(array_keys($courses), 'courses')
        ));
        while ($row = $compTextQuery->fetch(\PDO::FETCH_ASSOC)) {
            $componentName = $row['componentName'] ?? 'NULL';
            // if component is empty string, use the system default
            if (trim($componentName) === '') {
                $componentName = Textbox\Component\Homepage\Controller::DEFAULT_COMPONENT_HEADING;
            }
            //build the row and send
            fputcsv($fp, [
                $courses[$row['folder_id']]['course.name'],
                $courses[$row['folder_id']]['course.subject_code'],
                $courses[$row['folder_id']]['course.id'],
                $courses[$row['folder_id']]['course_specification.id'],
                $courses[$row['folder_id']]['framework'],
                $urlBase->withPath(sprintf("/homepage/%s", $courses[$row['folder_id']]['course.id']))->withFragment(sprintf("component%s", $row['componentId'])),
                $componentName,
                $row['componentId'] ?? 'NULL',
                $row['textboxHeading'] ?? 'NULL',
                '',
                $row['id'] ?? 'NULL',
                $row['textboxBody'] ?? 'NULL',
            ], escape: '');
        }

        // fetch all textboxes with no component from these units
        // clone with extension from \Schoolbox\Search\Api\Database\ResultType\Textbox\IndexBuilder::buildUpdates
        $textQuery = $database->prepareQuery("SELECT
            tb2.id AS id,
            tb2.heading AS 'textboxHeading',
            tb2.updated_at AS date,
            tb2.updated_by AS updated_by,
            tb2.body AS textboxBody,
            tb2.folder_id AS folderId
        FROM textbox tb2
                 LEFT JOIN component_config_instance cci2 ON (
            cci2.component_config_definition_id = (
                SELECT id
                FROM component_config_definition ccd2
                WHERE ccd2.key = :homepageComponentKey
            ) AND
            cci2.value = CAST(tb2.id AS NCHAR)
            )
        WHERE tb2.deleted_at IS NULL
          AND cci2.id IS NULL
          AND tb2.folder_id in {$coursePlaceHolder}
        ");

        /**
         * $rowHeadings = [
         * 'Course Title',
         * 'Subject Code',
         * 'Course Homepage ID',
         * 'Course ID',
         * 'Course Framework',
         * 'Edit Link',
         * 'Textbox Component Name',
         * 'Textbox Component ID',
         * 'Textbox Title',
         * 'New Textbox Title',
         * 'Textbox ID',
         * 'Textbox Content (empty string, empty state)'
         * ];
         */
        $textQuery->execute(array_merge(
            [
                'homepageComponentKey' => Textbox\Mapper::HOMEPAGE_COMPONENT_KEY
            ],
            Builder::getArrayParameters(array_keys($courses), 'courses')
        ));
        while ($row = $textQuery->fetch(\PDO::FETCH_ASSOC)) {
            //build the row and send
            fputcsv($fp, [
                $courses[$row['folderId']]['course.name'],
                $courses[$row['folderId']]['course.subject_code'],
                $courses[$row['folderId']]['course.id'],
                $courses[$row['folderId']]['course_specification.id'],
                $courses[$row['folderId']]['framework'],
                $urlBase->withPath(sprintf("/textbox/folder/%s", $courses[$row['folderId']]['course.id'])),
                'No Component',
                'No Component',
                $row['textboxHeading'] ?? 'NULL',
                '',
                $row['id'] ?? 'NULL',
                $row['textboxBody'] ?? 'NULL',
            ], escape: '');
        }

        fclose($fp);

        exit(1);
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * UNIT PLANNER
     * unitplannerComponents.csv
     * @api GET /adminv2/health-check-export/audit/unitplannerComponents
     * @since 24.1
     */
    public function unitplannerComponents()
    {
        $units = [];
        $database = FolderMapper::getInstance()->getDatabase();


        //build the csv
        $rowHeadings = [
            'Course Title',
            'Subject Code',
            'Course Homepage ID',
            'Course ID',
            'Course Framework',
            'Unit Title',
            'Unit Homepage ID',
            'Edit Link',
            'Component Type',
            'Component Title',
            'Curriculum Overview Tree Filter',
            'File Count',
            'Link Count',
        ];

        // base filename with school code and date
        $filename = sprintf("unit-audit-%s-%s.csv", $this->getConfigLoader()->get('site_school_code'), \Alaress_Date_Format::now()->toIso8601String());
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();

        // fetch all units/courses
        $courseUnitQuery = $database->prepareQuery("
        SELECT
            courseFolder.name AS 'course.name',
            course_specification.subject_code AS 'course.subject_code',
            courseFolder.id AS 'course.id',
            course_specification.id AS 'course_specification.id',
            unitFolder.name AS 'unit.name',
            unitFolder.id AS 'unit.id',
            courseTemplates.name AS 'framework'
        FROM course_specification
        JOIN courseTemplates ON course_specification.template_id = courseTemplates.id
        JOIN folder courseFolder ON course_specification.folder_id = courseFolder.id
        JOIN course_unit_specification ON course_specification.id = course_unit_specification.course_specification_id
        JOIN folder unitFolder ON course_unit_specification.folder_id = unitFolder.id
        WHERE course_specification.deleted_at IS NULL
        AND course_unit_specification.deleted_at IS NULL
        ");

        $courseUnitQuery->execute();
        while ($row = $courseUnitQuery->fetch(\PDO::FETCH_ASSOC)) {
            $units[$row['unit.id']] = $row;
        }

        $unitPlaceHolder = Builder::getArrayPlaceholders(array_keys($units), 'units');

        // fetch all textboxes with components from these units
        // clone with extension from \Schoolbox\Search\Api\Database\ResultType\Textbox\IndexBuilder::buildUpdates
        $curricOverviewQuery = $database->prepareQuery("SELECT
            f.id AS 'folder.id',
            cci_title.value AS 'component.name',
            ci.id AS 'component.id',
            cn.name AS 'tree.filter',
            cn.code AS 'tree.filter_code'
        FROM component_definition cd
                JOIN component_instance ci ON ci.component_definition_id = cd.id and ci.module = 'homepage'
                JOIN folder f ON f.id = ci.identifier
                JOIN        component_config_definition ccd_title ON
                            (
                            ccd_title.component_definition_id = cd.id AND
                            ccd_title.key = 'componentname'
                            )
                LEFT JOIN   component_config_instance cci_title ON (
                            cci_title.component_config_definition_id = ccd_title.id AND
                            cci_title.component_instance_id = ci.id
                            )
                JOIN        component_config_definition ccd_tree ON
                            (
                            ccd_tree.component_definition_id = cd.id AND
                            ccd_tree.key = 'curriculumTreeFilter'
                            )
                LEFT JOIN   component_config_instance cci_tree ON (
                            cci_tree.component_config_definition_id = ccd_tree.id AND
                            cci_tree.component_instance_id = ci.id
                            )
                LEFT JOIN   curriculum_node cn ON cci_tree.value = cn.id AND cn.deleted_at IS NULL
        WHERE cd.`class` = 'Schoolbox\\\Curriculum\\\Component\\\CurriculumOverviewController'
        AND f.deleted_at IS NULL
        AND f.id in {$unitPlaceHolder};
        ");


        header('Content-type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");

        $fp = fopen("php://output", 'w');
        fputcsv($fp, $rowHeadings, escape: '');

        /**
         * $rowHeadings = [
         * 'Course Title',
         * 'Subject Code',
         * 'Course Homepage ID',
         * 'Course ID',
         * 'Course Framework',
         * 'Unit Title',
         * 'Unit Homepage ID',
         * 'Edit Link',
         * 'Component Type',
         * 'Component Title',
         * 'Curriculum Overview Tree Filter',
         * 'File Count',
         * 'Link Count',
        * ];
         */
        $curricOverviewQuery->execute(
            Builder::getArrayParameters(array_keys($units), 'units')
        );

        while ($row = $curricOverviewQuery->fetch(\PDO::FETCH_ASSOC)) {
            $componentName = $row['componentName'] ?? 'NULL';
            // if component IS empty string, use the system default
            if (trim($componentName) === '') {
                $componentName = \Schoolbox\Curriculum\Component\CurriculumOverviewController::DEFAULT_COMPONENT_HEADING;
            }

            $curricCode = trim((string)$row['tree.filter_code']);
            $curricFilter = trim((string)$row['tree.filter']);

            if ('' !== $curricCode) {
                $curricFilter .= " [$curricCode]";
            }

            //build the row and send
            fputcsv($fp, [
                $units[$row['folder.id']]['course.name'],
                $units[$row['folder.id']]['course.subject_code'],
                $units[$row['folder.id']]['course.id'],
                $units[$row['folder.id']]['course_specification.id'],
                $units[$row['folder.id']]['framework'],
                $units[$row['folder.id']]['unit.name'],
                $units[$row['folder.id']]['unit.id'],
                $urlBase->withPath(sprintf("/homepage/%s", $units[$row['folder.id']]['unit.id']))->withFragment(sprintf("component%s", $row['component.id'])),
                'Curriculum Overview',
                $componentName,
                $curricFilter,
                null,
                null,
            ], escape: '');
        }

        $unitPlaceHolder = Builder::getArrayPlaceholders(array_keys($units), 'units');

        // fetch all textboxes with components from these units
        // clone with extension from \Schoolbox\Search\Api\Database\ResultType\Textbox\IndexBuilder::buildUpdates
        $fileQuery = $database->prepareQuery("SELECT
            f.id AS 'folder.id',
            cci_title.value AS 'component.name',
            ci.id AS 'component.id',
            cn.name AS 'tree.filter',
            cn.code AS 'tree.filter_code'
        FROM component_definition cd
                JOIN component_instance ci ON ci.component_definition_id = cd.id and ci.module = 'homepage'
                JOIN folder f ON f.id = ci.identifier
                JOIN        component_config_definition ccd_title ON
                            (
                            ccd_title.component_definition_id = cd.id AND
                            ccd_title.key = 'componentname'
                            )
                LEFT JOIN   component_config_instance cci_title ON (
                            cci_title.component_config_definition_id = ccd_title.id AND
                            cci_title.component_instance_id = ci.id
                            )
                JOIN        component_config_definition ccd_tree ON
                            (
                            ccd_tree.component_definition_id = cd.id AND
                            ccd_tree.key = 'curriculumTreeFilter'
                            )
                LEFT JOIN   component_config_instance cci_tree ON (
                            cci_tree.component_config_definition_id = ccd_tree.id AND
                            cci_tree.component_instance_id = ci.id
                            )
                LEFT JOIN   curriculum_node cn ON cci_tree.value = cn.id AND cn.deleted_at IS NULL
        WHERE cd.`class` = 'Schoolbox\\\Curriculum\\\Component\\\CurriculumOverviewController'
        AND f.deleted_at IS NULL
        AND f.id in {$unitPlaceHolder};
        ");

        /**
         * $rowHeadings = [
         * 'Course Title',
         * 'Subject Code',
         * 'Course Homepage ID',
         * 'Course ID',
         * 'Course Framework',
         * 'Unit Title',
         * 'Unit Homepage ID',
         * 'Edit Link',
         * 'Component Type',
         * 'Component Title',
         * 'Curriculum Overview Tree Filter',
         * 'File Count',
         * 'Link Count',
        * ];
         */
        $fileQuery->execute(
            Builder::getArrayParameters(array_keys($units), 'units')
        );

        while ($row = $fileQuery->fetch(\PDO::FETCH_ASSOC)) {
            $componentName = $row['component.name'] ?? 'NULL';
            // if component IS empty string, use the system default
            if (trim($componentName) === '') {
                $componentName = \Schoolbox\Curriculum\Component\CurriculumOverviewController::DEFAULT_COMPONENT_HEADING;
            }

            //build the row and send
            fputcsv($fp, [
                $units[$row['folder.id']]['course.name'],
                $units[$row['folder.id']]['course.subject_code'],
                $units[$row['folder.id']]['course.id'],
                $units[$row['folder.id']]['course_specification.id'],
                $units[$row['folder.id']]['framework'],
                $units[$row['folder.id']]['unit.name'],
                $units[$row['folder.id']]['unit.id'],
                $urlBase->withPath(sprintf("/homepage/%s", $units[$row['folder.id']]['unit.id']))->withFragment(sprintf("component%s", $row['component.id'])),
                'File Component',
                $componentName,
                null,
                'File Count',
                null,
            ], escape: '');
        }

        fclose($fp);

        exit(1);
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * UNIT PLANNER
     * unitplannerAssessments.csv
     * @api GET /adminv2/health-check-export/audit/unitplannerAssessments
     * @since 24.1
    */
    public function unitplannerAssessments()
    {

        $database = FolderMapper::getInstance()->getDatabase();
        $units = [];

        //build the csv
        $rowHeadings = [
            'Course Title',
            'Subject Code',
            'Course Homepage ID',
            'Course ID',
            'Course Framework',
            'Unit Title',
            'Unit Homepage ID',
            'Unit ID',
            'Assessment Type',
            'Work Type',
            'Count'
        ];

        // base filename with school code and date
        $filename = sprintf("unit-audit-%s-%s.csv", $this->getConfigLoader()->get('site_school_code'), \Alaress_Date_Format::now()->toIso8601String());
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();

        // fetch all courses & units & folders of them
        $courseQuery = $database->prepareQuery("
        SELECT
            courseFolder.name AS 'course.name',
            course_specification.subject_code AS 'course.subject_code',
            courseFolder.id AS 'course.id',
            course_specification.id AS 'course_specification.id',
            unitFolder.name AS 'unit.name',
            unitFolder.id AS 'unit.folder.id',
            courseTemplates.name AS 'framework',
            course_unit_specification.id AS 'unit.id'
        FROM course_specification
        JOIN courseTemplates ON course_specification.template_id = courseTemplates.id
        JOIN folder courseFolder ON course_specification.folder_id = courseFolder.id
        JOIN course_unit_specification ON course_specification.id = course_unit_specification.course_specification_id
        JOIN folder unitFolder ON course_unit_specification.folder_id = unitFolder.id
        WHERE course_specification.deleted_at IS NULL
        AND course_unit_specification.deleted_at IS NULL;
        ");

        $courseQuery->execute();
        while ($row = $courseQuery->fetch(\PDO::FETCH_ASSOC)) {
            $units[$row['unit.id']] = $row;
        }

        $coursePlaceHolder = Builder::getArrayPlaceholders(array_keys($units), 'units');

        // fetch all Assessments ON Unit pages within a Course
        $assessmentQuery = $database->prepareQuery("SELECT
            COALESCE(
                    IF(task.id, 'task', NULL),
                    IF(assess.assessment_id, 'quiz', NULL),
                    IF(lti_assessment.assessment_id, 'LTI', NULL),
                    IF(submission_box.id, 'dueWork', NULL),
                    IF(project.id, 'project', NULL),
                    IF(lesson_plan.id, 'lessonPlan', NULL)
            ) AS `assessment.type`,
            lu_work_type.name AS `work.type.name`,
            count(assessment.id) AS count,
            mv_assessment_relation.folder_id AS `folder_id`
        FROM mv_assessment_relation
                 JOIN assessment ON mv_assessment_relation.assessment_id = assessment.id
                 LEFT JOIN lu_work_type ON assessment.work_type_id = lu_work_type.id
                 LEFT JOIN task ON assessment.id = task.assessment_id
                 LEFT JOIN assess ON assessment.id = assess.assessment_id
                 LEFT JOIN lti_assessment ON assessment.id = lti_assessment.assessment_id
                 LEFT JOIN project ON assessment.id = project.assessment_id
                 LEFT JOIN submission_box ON assessment.id = submission_box.assessment_id
                 LEFT JOIN project projectParent ON projectParent.id = mv_assessment_relation.project_assessment_id
                 LEFT JOIN lesson_plan ON assessment.id = lesson_plan.assessment_id
                 JOIN      course_unit_specification ON mv_assessment_relation.course_unit_specification_id = course_unit_specification.id
        WHERE
            course_unit_specification.folder_id IN {$coursePlaceHolder}
        GROUP BY `assessment.type`, lu_work_type.id;
        ");


        header('Content-type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");

        $fp = fopen("php://output", 'w');
        fputcsv($fp, $rowHeadings, escape: '');

        /**
         * $rowHeadings = [
         * 'Course Title',
         * 'Course Homepage ID',
         * 'Course ID',
         * 'Course Framework',
         * 'Unit Title',
         * 'Unit Homepage ID',
         * 'Unit ID',
         * 'Assessment Type',
         * 'Work Type',
         * 'Count'
         * ];
         */
        $assessmentQuery->execute(
            Builder::getArrayParameters(array_keys($units), 'units')
        );
        while ($row = $assessmentQuery->fetch(\PDO::FETCH_ASSOC)) {
            //build the row and send
            fputcsv($fp, [
                $units[$row['folder_id']]['course.name'],
                $units[$row['folder_id']]['course.subject_code'],
                $units[$row['folder_id']]['course.id'],
                $units[$row['folder_id']]['course_specification.id'],
                $units[$row['folder_id']]['framework'],
                $units[$row['folder_id']]['unit.name'],
                $units[$row['folder_id']]['unit.folder.id'],
                $units[$row['folder_id']]['unit.id'],
                $row['assessment.type'] ?? 'NULL',
                $row['work.type.name'] ?? 'NULL',
                $row['count'] ?? 'NULL',
            ], escape: '');
        }

        fclose($fp);

        exit(1);
    }
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * UNIT PLANNER
     * unitplannerUnitTextboxes.csv
     * @api   GET /adminv2/health-check-export/audit/unitplannerUnitTextboxes
     * @since 24.1
     */
    public function unitplannerUnitTextboxes()
    {

        $database = FolderMapper::getInstance()->getDatabase();
        $courses = [];

        //build the csv
        $rowHeadings = [
            'Course Title',
            'Subject Code',
            'Course Homepage ID',
            'Course ID',
            'Course Framework',
            'Unit Title',
            'Unit Homepage ID',
            'Unit ID',
            'Edit Link',
            'Textbox Component Name',
            'Textbox Component ID',
            'Textbox Title',
            'New Textbox Title',
            'Textbox ID',
            'Textbox Content (empty string, empty state)'
        ];

        // base filename with school code and date
        $filename = sprintf("unit-audit-%s-%s.csv", $this->getConfigLoader()->get('site_school_code'), \Alaress_Date_Format::now()->toIso8601String());
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();

        // fetch all courses & units that aren't deleted
        $courseQuery = $database->prepareQuery("
        SELECT
            courseFolder.name AS 'course.name',
            course_specification.subject_code AS 'course.subject_code',
            courseFolder.id AS 'course.id',
            course_specification.id AS 'course_specification.id',
            course_unit_specification.id AS 'unit_specification.id',
            unitFolder.name AS 'unit.name',
            unitFolder.id AS 'unit.id',
            courseTemplates.name AS 'framework'
        FROM course_specification
        JOIN courseTemplates ON course_specification.template_id = courseTemplates.id
        JOIN folder courseFolder ON course_specification.folder_id = courseFolder.id
        JOIN course_unit_specification ON course_specification.id = course_unit_specification.course_specification_id
        JOIN folder unitFolder ON course_unit_specification.folder_id = unitFolder.id
        WHERE course_specification.deleted_at IS NULL
        AND course_unit_specification.deleted_at IS NULL
        ");

        $courseQuery->execute();
        while ($row = $courseQuery->fetch(\PDO::FETCH_ASSOC)) {
            $courses[$row['unit.id']] = $row;
        }

        $coursePlaceHolder = Builder::getArrayPlaceholders(array_keys($courses), 'courses');

        // fetch all textboxes with components from these courses
        // clone with extension from \Schoolbox\Search\Api\Database\ResultType\Textbox\IndexBuilder::buildUpdates
        $compTextQuery = $database->prepareQuery("SELECT
            tb.id AS id,
            f.id AS folder_id,
            cci_componentname.value AS 'componentName',
            ci.id AS 'componentId',
            tb.heading AS 'textboxHeading',
            tb.updated_at AS date,
            tb.updated_by AS updated_by,
            tb.body AS textboxBody
        FROM component_definition cd
                 JOIN component_instance ci ON ci.component_definition_id = cd.id and ci.module = 'homepage'
                 JOIN folder f ON f.id = ci.identifier
                 JOIN component_config_definition ccd_textbox ON (
            ccd_textbox.component_definition_id = cd.id AND
            ccd_textbox.key = :homepageComponentKey
            )
                 LEFT JOIN component_config_instance cci_textbox ON (
            cci_textbox.component_config_definition_id = ccd_textbox.id AND
            cci_textbox.component_instance_id = ci.id
            )
                 LEFT JOIN textbox tb ON CAST(tb.id AS NCHAR)=cci_textbox.value
                 JOIN component_config_definition ccd_componentname ON (
            ccd_componentname.component_definition_id = cd.id AND
            ccd_componentname.key = :componentConfigComponentNameKey
            )
                 LEFT JOIN component_config_instance cci_componentname ON (
            cci_componentname.component_config_definition_id = ccd_componentname.id AND
            cci_componentname.component_instance_id = ci.id
            )
        WHERE cd.`class` = 'Schoolbox\\\Resource\\\Textbox\\\Component\\\Homepage\\\Controller'
          AND f.deleted_at IS NULL
          AND tb.deleted_at IS NULL
        AND f.id in {$coursePlaceHolder};
        ");

        // Make sure this is being outputted as CSV and to be an attachment and not load inline
        header('Content-type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");

        $fp = fopen("php://output", 'w');
        fputcsv($fp, $rowHeadings, escape: '');

        /**
         * $rowHeadings = [
         * 'Course Title',
         * 'Subject Code',
         * 'Course Homepage ID',
         * 'Course ID',
         * 'Course Framework',
         * 'Unit Title',
         * 'Unit Homepage ID',
         * 'Unit ID',
         * 'Edit Link',
         * 'Textbox Component Name',
         * 'Textbox Component ID',
         * 'Textbox Title',
         * 'New Textbox Title',
         * 'Textbox ID',
         * 'Textbox Content (empty string, empty state)'
         * ];
         */
        $compTextQuery->execute(array_merge(
            [
                'homepageComponentKey' => Textbox\Mapper::HOMEPAGE_COMPONENT_KEY,
                'componentConfigComponentNameKey' => Component_Config_Componentname::KEY,
            ],
            Builder::getArrayParameters(array_keys($courses), 'courses')
        ));
        while ($row = $compTextQuery->fetch(\PDO::FETCH_ASSOC)) {
            $componentName = $row['componentName'] ?? 'NULL';
            // if component is empty string, use the system default
            if (trim($componentName) === '') {
                $componentName = Textbox\Component\Homepage\Controller::DEFAULT_COMPONENT_HEADING;
            }
            //build the row and send
            fputcsv($fp, [
                $courses[$row['folder_id']]['course.name'],
                $courses[$row['folder_id']]['course.subject_code'],
                $courses[$row['folder_id']]['course.id'],
                $courses[$row['folder_id']]['course_specification.id'],
                $courses[$row['folder_id']]['framework'],
                $courses[$row['folder_id']]['unit.name'],
                $courses[$row['folder_id']]['unit.id'],
                $courses[$row['folder_id']]['unit_specification.id'],
                $urlBase->withPath(sprintf("/homepage/%s", $courses[$row['folder_id']]['unit.id']))->withFragment(sprintf("component%s", $row['componentId'])),
                $componentName,
                $row['componentId'] ?? 'NULL',
                $row['textboxHeading'] ?? 'NULL',
                '',
                $row['id'] ?? 'NULL',
                $row['textboxBody'] ?? 'NULL',
            ], escape: '');
        }

        // fetch all textboxes not currently housed in a component on the page
        // clone with extension from \Schoolbox\Search\Api\Database\ResultType\Textbox\IndexBuilder::buildUpdates
        $textQuery = $database->prepareQuery("SELECT
            tb2.id AS id,
            tb2.heading AS 'textboxHeading',
            tb2.updated_at AS date,
            tb2.updated_by AS updated_by,
            tb2.body AS textboxBody,
            tb2.folder_id AS folderId
        FROM textbox tb2
                 LEFT JOIN component_config_instance cci2 ON (
            cci2.component_config_definition_id = (
                SELECT id
                FROM component_config_definition ccd2
                WHERE ccd2.key = :homepageComponentKey
            ) AND
            cci2.value = CAST(tb2.id AS NCHAR)
            )
        WHERE tb2.deleted_at IS NULL
          AND cci2.id IS NULL
          AND tb2.folder_id in {$coursePlaceHolder}
        ");

        /**
         * $rowHeadings = [
         * 'Course Title',
         * 'Subject Code',
         * 'Course Homepage ID',
         * 'Course ID',
         * 'Course Framework',
         * 'Unit Title',
         * 'Unit Homepage ID',
         * 'Unit ID',
         * 'Edit Link',
         * 'Textbox Component Name',
         * 'Textbox Component ID',
         * 'Textbox Title',
         * 'New Textbox Title',
         * 'Textbox ID',
         * 'Textbox Content (empty string, empty state)'
         * ];
         */
        $textQuery->execute(array_merge(
            [
                'homepageComponentKey' => Textbox\Mapper::HOMEPAGE_COMPONENT_KEY
            ],
            Builder::getArrayParameters(array_keys($courses), 'courses')
        ));
        while ($row = $textQuery->fetch(\PDO::FETCH_ASSOC)) {
            //build the row and send
            fputcsv($fp, [
                $courses[$row['folderId']]['course.name'],
                $courses[$row['folderId']]['course.subject_code'],
                $courses[$row['folderId']]['course.id'],
                $courses[$row['folderId']]['course_specification.id'],
                $courses[$row['folderId']]['framework'],
                $courses[$row['folderId']]['unit.name'],
                $courses[$row['folderId']]['unit.id'],
                $courses[$row['folderId']]['unit_specification.id'],
                $urlBase->withPath(sprintf("/textbox/folder/%s", $courses[$row['folderId']]['course.id'])),
                'No Component',
                'No Component',
                $row['textboxHeading'] ?? 'NULL',
                '',
                $row['id'] ?? 'NULL',
                $row['textboxBody'] ?? 'NULL',
            ], escape: '');
        }

        fclose($fp);

        exit(1);
    }

    // ===============================================================================================
    // ================================ END UNIT PLANNER REPORTS =====================================
    // ===============================================================================================

    // ===============================================================================================
    // ================================ START OLC STATS REPORTS ======================================
    // ===============================================================================================

    /**
     * OLCSTATS
     * olcstatsQuizAttempts.csv
     */

    public function olcstatsQuizAttempts()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            concat((YEAR(aa.start)),' ',MONTHNAME(aa.start)) AS 'Month',
            count(*) AS 'Quiz Attempts'
        FROM
            course_implementation ci
        JOIN
            mv_assessment_relation mvar ON ci.id = mvar.course_implementation_id AND ci.course_specification_id = '34'
        JOIN
            assess a ON mvar.assessment_id = a.assessment_id
        JOIN
            assess_attempt aa ON a.assess_id = aa.assess_id AND aa.deleted_at is NULL AND aa.finish IS NOT NULL
        GROUP BY     YEAR(aa.start),MONTHNAME(aa.start)
        ORDER BY     YEAR(aa.start),MONTH(aa.start);
        ");
        $statement->execute();
        $help = new Helper();
        $help->queryToCSV($statement, 'olcstatsQuizAttempts.csv');
    }

    /**
     * OLCSTATS
     * olcstatsTaskCompletions.csv
     */

    public function olcstatsTaskCompletions()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            concat((YEAR(tc.completion_date)),' ',MONTHNAME(tc.completion_date)) AS 'Month',
            count(*) AS 'Quiz Attempts'
        FROM
            course_implementation ci
        JOIN
            mv_assessment_relation mvar ON ci.id = mvar.course_implementation_id AND ci.course_specification_id = '34'
        JOIN
            task t ON mvar.assessment_id = t.assessment_id
        JOIN
            task_completion tc ON t.id = tc.task_id and tc.completion_status = '3'
        GROUP BY
            YEAR(tc.completion_date),MONTHNAME(tc.completion_date)
        ORDER BY
            YEAR(tc.completion_date),MONTH(tc.completion_date);
        ");
        $statement->execute();
        $help = new Helper();
        $help->queryToCSV($statement, 'olcstatsTaskCompletions.csv');
    }

    /**
     * OLCSTATS
     * olcstatsDueWorkSubmissions.csv
     */

    public function olcstatsDueWorkSubmissions()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT concat((YEAR(ss.submitted_at)),' ',MONTHNAME(ss.submitted_at)) as 'Month',
            count(*) as 'Submissions'
        FROM
            course_implementation ci
        JOIN
            mv_assessment_relation mvar on ci.id = mvar.course_implementation_id and ci.course_specification_id = '34'
        JOIN
            submission_box sb on mvar.assessment_id = sb.assessment_id
        JOIN
            student_submission ss on sb.id = ss.box_id and ss.submitted_at is NOT NULL
        JOIN
            assessment a on sb.assessment_id = a.id and a.deleted_at is NULL
        GROUP BY
            YEAR(ss.submitted_at),MONTHNAME(ss.submitted_at)
        ORDER BY
            YEAR(ss.submitted_at),MONTH(ss.submitted_at);
        ");
        $statement->execute();
        $help = new Helper();
        $help->queryToCSV($statement, 'olcstatsDueWorkSubmissions.csv');
    }

    // ===============================================================================================
    // ================================= END OLC STATS REPORTS =======================================
    // ===============================================================================================
}
