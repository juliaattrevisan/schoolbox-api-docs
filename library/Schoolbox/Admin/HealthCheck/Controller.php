<?php

namespace Schoolbox\Admin\HealthCheck;

use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\MessageFactory;

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
     */

    /**
     * Constructor
     *
     * @param Jwt            $tokenBuilder
     * @param IConfigLoader  $config
     * @param Router         $router
     * @param MessageFactory $messageFactory
     * @param Producer       $producer
     */
    public function __construct(
        Jwt $tokenBuilder,
        IConfigLoader $config,
        Router $router,
        MessageFactory $messageFactory,
        Producer $producer
    ) {
        $this->tokenBuilder = $tokenBuilder;

        $this->taskDHelper = new TaskD(
            $this->tokenBuilder,
            $config,
            $router,
            $messageFactory,
            $producer
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
        $query = $this->getRequest()->query->get('report');
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
                    $this->taskDHelper->addSource('assessment', 'assessment', $urlBase->withPath($this->getRouter()->generate('learning.getAssessmentAudit', ['folderId' => 0])));
                    break;
                case 'uxui':
                    /**
                     * list of reports for uxui
                     */
                    $this->taskDHelper->addSource('uxui', 'topMenuReport');
                    $this->taskDHelper->addSource('uxui', 'sideMenuReport');
                    $this->taskDHelper->addSource('uxui', 'resourceBrowser');
                    $this->taskDHelper->addSource('uxui', 'homepageTemplates');
                    break;
                case 'user':
                    /**
                     * list of reports for user management audit
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
                     * list of reports for LMS Fundamentals Audit
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
                     * list of reports for Community Engagement Audit
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
                     * list of reports for implementation reports
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
//                    $this->taskDHelper->addSource('', 'implementationAcademicPeriods');
                    break;
                default:
                    throw new SchoolboxException("Report '{$report}' Does not exist.");
            }
        }

        //after looping through all reports, send them all to here...
        $this->taskDHelper->sendToTaskD('HealthCheck', $this->getUser());

        //redirect home
        $this->getResponse()->redirect->found('/');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'HomepageTemplates.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'TopMenuReport.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'SideMenuReport.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'ResourceBrowser.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'listOfRoles.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'listOfCampuses.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'listOfYearLevels.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'listOfHouses.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'servicePermissions.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'listOfSuperusers.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'gradeGroups.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'academicPeriods.csv');
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
            IF(staff_or_instructor_visible_only, 'Yes', 'No') as 'Visible to Staff only'
        FROM
            lu_work_type
        WHERE
            deleted_at IS NULL
        ORDER BY
            sequence ASC;
        ");
        $statement->execute();
        $help = new Helper();
        $help->queryToCSV($statement, 'workTypes.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'submissionStates.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'lmsSettings.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'yearLevelPermissions.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'newsTopics.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'newsServicePermissions.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'newsSettings.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'eventTypes.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'calendarServicePermissions.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'parentNewsNotifications.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaMonthlyLogins.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaNewsArticleViews.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaNewsArticleViewsPerRolePerArticle.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaNewsArticleViewsPerMonthBySKC.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaNewsArticleViewsPerMonthByActiveAlumni.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaNewsCreatedBySVATeam.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaHomepageViewsByKSC.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaHomepageViewsByAllPerRolePerMonth.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaHomepageViewsForCommunityForumsPage.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaHomepageViewsForOpportunitiesPage.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaHomepageViewsForProfessionalReadingPage.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaHomepageViewsForEvaluationPage.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaHomepageViewsForActiveAlumniPage.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaHomepageViewsForRrrHubSchoolsPage.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaForumThreadsViews.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'svaForumReplies.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'implementationServicePermissions.csv');
    }
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
        $help = new Helper();
        $help->queryToCSV($statement, 'implementationRoleList.csv');
    }
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
        $help = new Helper();
        $help->queryToCSV($statement, 'implementationCampusList.csv');
    }
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
        $help = new Helper();
        $help->queryToCSV($statement, 'implementationHouseList.csv');
    }
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
        $help = new Helper();
        $help->queryToCSV($statement, 'implementationYearLevels.csv');
    }
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
        $help = new Helper();
        $help->queryToCSV($statement, 'implementationNewsTopics.csv');
    }
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
        $help = new Helper();
        $help->queryToCSV($statement, 'implementationCalendarEventTypes.csv');
    }
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
        $help = new Helper();
        $help->queryToCSV($statement, 'implementationNotificationSettings.csv');
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
        $help = new Helper();
        $help->queryToCSV($statement, 'implementationSideMenuReport.csv');
    }
}
