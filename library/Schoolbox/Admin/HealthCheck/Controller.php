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
        //list of queries to run, if the request does not include one of these, error out.
        $validReports = ['assessment', 'uxui', 'user', 'lms', 'community'];
        $query = $this->getRequest()->query->get('report');
        if ('' === trim((string)$query)) {
            //if no value was handed, just assume the user was after everything
            $query = 'all';
        }

        //array of reports to be run
        $reports = explode(',', $query);
        //if query is 'all' lets add all options in
        if ('all' === $query) {
            $reports = $validReports;
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
                    $this->taskDHelper->addSource('assessment', 'assessment', $urlBase->setPath($this->getRouter()->generate('learning.getAssessmentAudit', ['folderId' => 0]))->toString());
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
        $help->queryToCSV($statement, 'HomepageTempates.csv');
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
            lc.name as 'Campus',
            (SELECT
                group_concat(g.name)
            FROM `groups` g
            LEFT JOIN
                menu_top_role mtr ON mtr.menu_top_id = mt.id
            WHERE
                mtr.role_id = g.id
            AND
                g.deleted_at IS NULL
            ) as 'Role'
        FROM
            menu_top mt
        LEFT JOIN
            menu_top_campus mtc on mtc.menu_top_id = mt.id
        LEFT JOIN
            luCampus lc on mtc.campus_id = lc.id
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
            lc.name as 'Campus',
            (SELECT
                group_concat(g.name)
            FROM
                `groups` g
            LEFT JOIN
                menu_side_role msr on msr.menu_side_id = ms.id
            WHERE
                msr.role_id = g.id
            AND
                g.deleted_at IS NULL
            ) AS 'Role'
        FROM
            menu_side ms
        LEFT JOIN
            menu_side_campus msc on msc.menu_side_id = ms.id
        LEFT JOIN
            luCampus lc on msc.campus_id = lc.id
        GROUP BY
            ms.title, ms.url
        ORDER BY
            ms.title, lc.name;
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
}
