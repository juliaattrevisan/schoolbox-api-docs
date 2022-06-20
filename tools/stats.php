<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Schoolbox\IConfigLoader;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * Queries the database for various different key indicators of engagement and pipes the data in JSON to STDOUT.
 * This data is used to populate our engagement database in influxDB and populate the graphs in monitor.schoolbox.com.au
 * Logging is piped to STDERR
 */
$app = new class extends CliTool\App {
    public function run(): void
    {
        /* @var IConnector $db */
        $db = $this->getDi()->get('databaseInternal');
        /** @var IConfigLoader $config */
        $config = $this->getDi()->get('config');

        $queryList = [
            //Users
            'students_enabled'  => 'SELECT count(*) FROM user u WHERE u.deleted_at IS NULL AND enabled = 1 AND u.group_id IN (SELECT id FROM `groups` g WHERE g.group_type = "student")',
            'students_disabled' => 'SELECT count(*) FROM user u WHERE u.deleted_at IS NULL AND enabled <> 1 AND u.group_id IN (SELECT id FROM `groups` g WHERE g.group_type = "student")',
            'students_deleted'  => 'SELECT count(*) FROM user u WHERE u.deleted_at IS not NULL AND u.group_id IN (SELECT id FROM `groups` g WHERE g.group_type = "student")',
            'staff_enabled'     => 'SELECT count(*) FROM user u WHERE u.deleted_at IS NULL AND enabled = 1 AND u.group_id IN (SELECT id FROM `groups` g WHERE g.group_type = "staff")',
            'staff_disabled'    => 'SELECT count(*) FROM user u WHERE u.deleted_at IS NULL AND enabled <> 1 AND u.group_id IN (SELECT id FROM `groups` g WHERE g.group_type = "staff")',
            'staff_deleted'     => 'SELECT count(*) FROM user u WHERE u.deleted_at IS not NULL AND u.group_id IN (SELECT id FROM `groups` g WHERE g.group_type = "staff")',
            'parents_enabled'   => 'SELECT count(*) FROM user u WHERE u.deleted_at IS NULL AND enabled = 1 AND u.group_id IN (SELECT id FROM `groups` g WHERE g.group_type = "parent")',
            'parents_disabled'  => 'SELECT count(*) FROM user u WHERE u.deleted_at IS NULL AND enabled <> 1 AND u.group_id IN (SELECT id FROM `groups` g WHERE g.group_type = "parent")',
            'parents_deleted'   => 'SELECT count(*) FROM user u WHERE u.deleted_at IS not NULL AND u.group_id IN (SELECT id FROM `groups` g WHERE g.group_type = "parent")',
            'guests_enabled'    => 'SELECT count(*) FROM user u WHERE u.deleted_at IS NULL AND enabled = 1 AND u.group_id IN (SELECT id FROM `groups` g WHERE g.group_type = "guest")',
            'guests_disabled'   => 'SELECT count(*) FROM user u WHERE u.deleted_at IS NULL AND enabled <> 1 AND u.group_id IN (SELECT id FROM `groups` g WHERE g.group_type = "guest")',
            'guests_deleted'    => 'SELECT count(*) FROM user u WHERE u.deleted_at IS not NULL AND u.group_id IN (SELECT id FROM `groups` g WHERE g.group_type = "guest")',

            //Logins
            'unique_logins_yesterday' => 'SELECT COUNT(DISTINCT username) FROM login_log WHERE login = 1 and DATE(date) = SUBDATE(DATE(NOW()),INTERVAL 1 DAY) GROUP BY DATE(date)',
            'unique_logins_yesterday_student' => 'SELECT COUNT( DISTINCT login_log.username) FROM login_log JOIN user ON user.username = login_log.username JOIN `groups` ON group_id = `groups`.id WHERE login = 1 and DATE (date) = SUBDATE(DATE(NOW()),INTERVAL 1 DAY)  AND group_type = "student" GROUP BY DATE(date)',
            'unique_logins_yesterday_staff' => 'SELECT COUNT( DISTINCT login_log.username) FROM login_log JOIN user ON user.username = login_log.username JOIN `groups` ON group_id = `groups`.id WHERE login = 1 and DATE (date) = SUBDATE(DATE(NOW()),INTERVAL 1 DAY)  AND group_type = "staff" GROUP BY DATE(date)',
            'unique_logins_yesterday_parent' => 'SELECT COUNT( DISTINCT login_log.username) FROM login_log JOIN user ON user.username = login_log.username JOIN `groups` ON group_id = `groups`.id WHERE login = 1 and DATE (date) = SUBDATE(DATE(NOW()),INTERVAL 1 DAY)  AND group_type = "parent" GROUP BY DATE(date)',
            'unique_logins_yesterday_guest' => 'SELECT COUNT( DISTINCT login_log.username) FROM login_log JOIN user ON user.username = login_log.username JOIN `groups` ON group_id = `groups`.id WHERE login = 1 and DATE (date) = SUBDATE(DATE(NOW()),INTERVAL 1 DAY)  AND group_type = "guest" GROUP BY DATE(date)',
            'logins_student'    => 'SELECT count(*) FROM login_log JOIN user ON user.username = login_log.username JOIN `groups` ON user.group_id = `groups`.id WHERE login = 1 and `groups`.group_type = "student"',
            'logins_staff'    => 'SELECT count(*) FROM login_log JOIN user ON user.username = login_log.username JOIN `groups` ON user.group_id = `groups`.id WHERE login = 1 and `groups`.group_type = "staff"',
            'logins_parent'    => 'SELECT count(*) FROM login_log JOIN user ON user.username = login_log.username JOIN `groups` ON user.group_id = `groups`.id WHERE login = 1 and `groups`.group_type = "parent"',
            'logins_guests'    => 'SELECT count(*) FROM login_log JOIN user ON user.username = login_log.username JOIN `groups` ON user.group_id = `groups`.id WHERE login = 1 and `groups`.group_type = "guest"',
            'logins'            => 'SELECT count(*) FROM login_log l WHERE login = 1',

            //LMS
            'classes'           => 'SELECT count(f.id) from folder f JOIN folder_code fc ON f.id = fc.folder_id where f.deleted_at is null and fc.deleted_at is null',
            'classes_with_course_implementations' => 'SELECT count(*) FROM folder f WHERE f.id IN (SELECT ci.folder_id FROM course_implementation ci WHERE ci.deleted_at IS NULL) AND f.id IN (SELECT folder_id FROM folder_code)',
            'class_assessments' => 'SELECT COUNT(*) FROM assessment JOIN assessment_folder af on assessment.id = af.assessment_id WHERE assessment.deleted_at IS NULL',
            'student_submissions' => 'SELECT COUNT(*) FROM student_submission WHERE deleted_at IS NULL',
            'feedback_self_graded' => 'SELECT COUNT(*) FROM submission_return WHERE deleted_at IS  NULL AND review_route = "self-grading"',
            'feedback_teacher'  => 'SELECT COUNT(*) FROM submission_return WHERE deleted_at IS  NULL AND review_route = "instructor"',
            'lti_response'      => 'SELECT COUNT(*) FROM lti_assessment_score WHERE deleted_at IS NULL AND is_response = 1',
            'lti_feedback'      => 'SELECT COUNT(*) FROM lti_assessment_score WHERE deleted_at IS NULL AND is_feedback = 1',
            'course_unit_implementations' => 'SELECT count(*) FROM course_unit_implementation ci WHERE ci.deleted_at IS NULL',
            'course_unit_specifications'  => 'SELECT count(*) FROM course_unit_specification cs WHERE cs.deleted_at IS NULL',
            'course_specifications'       => 'SELECT COUNT(*) FROM course_specification WHERE deleted_at IS NULL',
            'course_implementation_assessments' => 'SELECT COUNT(*) FROM assessment JOIN assessment_course_unit_specification a on assessment.id = a.assessment_id WHERE assessment.deleted_at IS NULL',
            'course_due_work'   => 'SELECT COUNT(*) FROM assessment JOIN assessment_course_unit_specification a on assessment.id = a.assessment_id JOIN submission_box ON assessment.id = submission_box.assessment_id WHERE assessment.deleted_at IS NULL',
            'course_quiz'       => 'SELECT COUNT(*) FROM assessment JOIN assessment_course_unit_specification a on assessment.id = a.assessment_id JOIN assess ON assessment.id = assess.assessment_id WHERE assessment.deleted_at IS NULL',
            'course_tasks'      => 'SELECT COUNT(*) FROM assessment JOIN assessment_course_unit_specification a on assessment.id = a.assessment_id JOIN task ON assessment.id = task.assessment_id WHERE assessment.deleted_at IS NULL',
            'course_projects'   => 'SELECT COUNT(*) FROM assessment JOIN assessment_course_unit_specification a on assessment.id = a.assessment_id JOIN project ON assessment.id = project.assessment_id WHERE assessment.deleted_at IS NULL',
            'assessments'       => 'SELECT count(*) FROM assessment WHERE deleted_at IS NULL',
            'quizzes'           => 'SELECT count(*) FROM assess WHERE availability = "assess" AND deleted_at IS NULL',
            'due_works'         => 'SELECT count(*) FROM submission_box sb JOIN assessment a ON a.id = sb.assessment_id WHERE a.deleted_at IS NULL',
            'projects'          => 'SELECT count(*) FROM project JOIN assessment a ON a.id = project.assessment_id WHERE a.deleted_at IS NULL',
            'tasks'             => 'SELECT count(*) FROM task JOIN assessment a ON a.id = task.assessment_id WHERE a.deleted_at IS NULL',
            'lti_assessment'    => 'SELECT count(*) FROM lti_assessment JOIN assessment a on lti_assessment.assessment_id = a.id WHERE a.deleted_at IS NULL',
            'due_work_rubrics'  => 'SELECT COUNT(*) FROM assessment JOIN submission_box ON assessment.id = submission_box.assessment_id WHERE assessment.deleted_at IS NULL AND use_rubric = 1',
            'due_work_self_assessments' => 'SELECT COUNT(*) FROM assessment JOIN submission_box ON assessment.id = submission_box.assessment_id WHERE assessment.deleted_at IS NULL AND self_reviewability != "none"',
            'due_work_online_submission' => 'SELECT COUNT(*) FROM assessment JOIN submission_box ON assessment.id = submission_box.assessment_id WHERE assessment.deleted_at IS NULL AND accept_submission = 1',
            'due_work_offline_submission' => 'SELECT COUNT(*) FROM assessment JOIN submission_box ON assessment.id = submission_box.assessment_id WHERE assessment.deleted_at IS NULL AND accept_submission = 0',
            'assessment_visibility_staff' => 'SELECT COUNT(*) FROM assessment JOIN submission_box ON assessment.id = submission_box.assessment_id WHERE assessment.deleted_at IS NULL AND mark_visibility = 0',
            'assessment_visibility_student' => 'SELECT COUNT(*) FROM assessment JOIN submission_box ON assessment.id = submission_box.assessment_id WHERE assessment.deleted_at IS NULL AND mark_visibility = 1',
            'assessment_visibility_parent' => 'SELECT COUNT(*) FROM assessment JOIN submission_box ON assessment.id = submission_box.assessment_id WHERE assessment.deleted_at IS NULL AND mark_visibility = 2',
            'kami_docs'         => 'SELECT count(*) FROM student_submission_file_kami WHERE status = "done" and deleted_at is null',
            'kami_processing'   => 'SELECT count(*) FROM student_submission_file_kami WHERE status = "processing" and deleted_at is null',
            'kami_errors'       => 'SELECT count(*) FROM student_submission_file_kami WHERE status = "error" and deleted_at is null',
            'turnitin_docs'     => 'SELECT count(*) FROM turnitin_report WHERE status = "complete" and deleted_at is null',
            'turnitin_errors'   => 'SELECT count(*) FROM turnitin_report WHERE status = "error" and deleted_at is null',

            //Homepages
            'forum_posts'       => 'SELECT count(*) FROM `forumPost` WHERE `deleted` <> "true"',
            'forum_thread'      => 'SELECT count(*) FROM forumThread WHERE deleted = "false"',
            'social_stream_posts' => 'SELECT count(*) FROM `socialstream_post` WHERE `deleted_at` IS NULL',
            'surveys'           => 'SELECT count(*) FROM assess WHERE availability = "survey" AND deleted_at IS NULL',
            'polls'             => 'SELECT count(*) FROM poll',
            'tiles'             => 'SELECT count(*) FROM tile WHERE deleted_at IS NULL',
            'textbox'           => 'SELECT count(*) FROM textBox WHERE deleted IS NULL',
            'wiki'              => 'SELECT count(*) FROM wiki WHERE deleted = "false"',
            'chat'              => 'SELECT count(*) FROM chat',
            'blog'              => 'SELECT count(*) FROM blog WHERE deleted_at IS NULL',
            'etherpad'          => 'SELECT count(*) FROM etherpad_pad WHERE deleted_at IS NULL',
            'groups'            => 'SELECT COUNT(*) FROM userGroups JOIN folder f on userGroups.cid = f.id WHERE f.deleted_at is null',
            'groups_open'       => 'SELECT COUNT(*) FROM userGroups JOIN folder f on userGroups.cid = f.id WHERE userGroups.type = ' . \Schoolbox\Resource\Group::FREE_GROUP . ' AND f.deleted_at is null',
            'groups_moderated'  => 'SELECT COUNT(*) FROM userGroups JOIN folder f on userGroups.cid = f.id WHERE userGroups.type = ' . \Schoolbox\Resource\Group::MODERATED_GROUP . ' AND f.deleted_at is null',
            'groups_private'    => 'SELECT COUNT(*) FROM userGroups JOIN folder f on userGroups.cid = f.id WHERE userGroups.type = ' . \Schoolbox\Resource\Group::PRIVATE_GROUP . ' AND f.deleted_at is null',
            'views_total'       => 'SELECT SUM(hitcount) FROM homepage_log',
            'views_groups'      => 'SELECT SUM(hitcount) FROM homepage_log JOIN userGroups u on homepage_log.folder_id = u.cid',
            'views_classes'     => 'SELECT SUM(hitcount) FROM homepage_log JOIN folder_code fc ON homepage_log.folder_id = fc.folder_id',
            'views_course_specifications' => 'SELECT SUM(hitcount) FROM homepage_log JOIN course_specification cs ON homepage_log.folder_id = cs.folder_id',
            'views_course_unit_specifications' => 'SELECT SUM(hitcount) FROM homepage_log JOIN course_unit_specification ON course_unit_specification.folder_id = homepage_log.folder_id',

            //News
            'news_posts'    => 'SELECT count(*) FROM comms_news_article WHERE deleted_at is null',
            'news_posts_comments_allowed'   => 'SELECT COUNT(*) FROM discussion_thread dt INNER JOIN comms_news_article cna ON dt.id = cna.discussion_thread_id WHERE cna.deleted_at IS NULL AND dt.open IS TRUE',
            'news_comments' => 'SELECT count(*) FROM comms_news_article JOIN discussion_thread ON discussion_thread_id = discussion_thread.id AND comms_news_article.deleted_at is null JOIN discussion_thread_comment ON discussion_thread.id = discussion_thread_comment.discussion_thread_id WHERE discussion_thread_comment.deleted_at IS NULL',
            'news_saveforlater' => 'SELECT count(*) FROM comms_news_saveforlater WHERE deleted_at IS NULL',
            'news_files'        => 'SELECT count(*) FROM comms_news_article_file WHERE deleted_at IS NULL',

            //ePortfolio
            'eportfolio_pages'  => 'SELECT count(*) FROM `eportfolio_page` WHERE deleted_at IS NULL',
            'eportfolio_enabled_student' => 'SELECT COUNT(*) FROM user JOIN `groups` ON user.group_id = `groups`.id WHERE user.deleted_at is null AND eportfolio_enabled = 1 and group_type = "student"',
            'eportfolio_enabled_staff' => 'SELECT COUNT(*) FROM user JOIN `groups` ON user.group_id = `groups`.id WHERE user.deleted_at is null AND eportfolio_enabled = 1 and group_type = "staff"',
            'eportfolio_blog_posts' => 'SELECT COUNT(*) FROM eportfolio_component_blog WHERE deleted_at is null',
            'showcased_work'        => 'SELECT COUNT(*) FROM eportfolio_showcase_item WHERE deleted_at is null',

            //Resource Booking
            'resource_booking_items'    => 'SELECT COUNT(*) FROM resource WHERE deleted_at IS NULL',
            'resource_booking_events'   => 'SELECT COUNT(*) FROM resource_booking WHERE deleted_at IS NULL',

            //Fixtures
            'fixtures_teams'            => 'SELECT COUNT(*) FROM sportsfixtures_team WHERE deleted_at IS NULL',
            'fixtures_matches'            => 'SELECT COUNT(*) FROM sportsfixtures_match WHERE deleted_at IS NULL',

            //Pastoral
            'pastoral_records'  => 'SELECT count(*) FROM `pastoral` WHERE deleted_at IS NULL',
            'pastoral_groups'   => 'SELECT count(*) FROM `pastoral_group` WHERE deleted_at IS NULL',
            'pastoral_flags'   => 'SELECT COUNT(*) FROM lu_pastoral_type lpt LEFT JOIN lu_pastoral_subtype lps on lpt.id = lps.pastoral_type_id WHERE (lpt.icon IS NOT NULL OR lps.icon IS NOT NULL) AND lpt.deleted_at IS NULL AND lps.deleted_at IS NULL ',
            'pastoral_templates'   => 'SELECT COUNT(*) FROM lu_pastoral_type lpt LEFT JOIN lu_pastoral_subtype lps on lpt.id = lps.pastoral_type_id WHERE
            ((lpt.template IS NOT NULL AND  lpt.template != "") OR (lps.template IS NOT NULL AND lps.template != "")) AND lpt.deleted_at IS NULL AND lps.deleted_at IS NULL',
            'pastoral_actions_total'  => 'SELECT COUNT(*) FROM pastoral_record_action WHERE deleted_at IS NULL',
            'pastoral_actions_completed' => 'SELECT COUNT(*) FROM pastoral_record_action WHERE completed = 1 AND deleted_at IS NULL',
            'pastoral_comment'   => 'SELECT COUNT(*) FROM pastoral_comment WHERE deleted_at IS NULL',

            //EOTC
            'eotc_events'       => 'SELECT count(*) FROM eotc_event WHERE deleted_at IS NULL',

            //Boarder Leave
            'leave_requests'    => 'SELECT count(*) FROM leave_request',

            //Curriculum
            'curriculum'                    => 'SELECT count(*) FROM curriculum_node WHERE deleted_at IS NULL',
            'curriculum_assessment'         => 'SELECT count(*) FROM curriculum_node JOIN curriculum_node_for_assessment cnfa on curriculum_node.id = cnfa.curriculum_node_id WHERE curriculum_node.deleted_at IS NULL',
            'curriculum_rubric_indicator'   => 'SELECT count(*) FROM curriculum_node JOIN curriculum_node_for_rubric_indicator cnfri on curriculum_node.id = cnfri.curriculum_node_id WHERE curriculum_node.deleted_at IS NULL',
            'curriculum_rubric_capability'  => 'SELECT count(*) FROM curriculum_node JOIN curriculum_node_for_rubric_capability cnfrc on curriculum_node.id = cnfrc.curriculum_node_id WHERE curriculum_node.deleted_at IS NULL',
            'curriculum_folder'             => 'SELECT count(*) FROM curriculum_node JOIN curriculum_node_for_category cnfc on curriculum_node.id = cnfc.curriculum_node_id WHERE curriculum_node.deleted_at IS NULL',
            'curriculum_badge'              => 'SELECT count(*) FROM curriculum_node JOIN curriculum_badge cb on curriculum_node.id = cb.curriculum_node_id WHERE curriculum_node.deleted_at IS NULL',
            'curriculum_evidence'           => 'SELECT count(*) FROM curriculum_node JOIN curriculum_evidence ce on curriculum_node.id = ce.curriculum_node_id WHERE curriculum_node.deleted_at IS NULL',

            //Mobile Users
            'mobile_registrations' => 'SELECT count(*) FROM `mobile_app_registry`',

            //Calendar
            'event_school'      => 'SELECT COUNT(*) FROM event WHERE type = ' . Schoolbox\Calendar\Event::ACCESS_CAMPUS . ' AND deleted_at IS NULL',
            'event_group'       => 'SELECT COUNT(*) FROM event WHERE type = ' . Schoolbox\Calendar\Event::ACCESS_GROUP . ' AND deleted_at IS NULL',
            'event_personal'    => 'SELECT COUNT(*) FROM event WHERE type = ' . Schoolbox\Calendar\Event::ACCESS_PERSONAL . ' AND deleted_at IS NULL',

            //CMS
            'storage'           => 'SELECT count(*) FROM `storage`',
            'storage_size'      => 'SELECT sum(filesize) FROM `storage`',
            'files'             => 'SELECT COUNT(*) FROM file WHERE deleted_at IS NULL',
            'links'             => 'SELECT count(*) FROM link WHERE deleted_at IS NULL',
            'folders'           => 'SELECT count(*) FROM folder WHERE deleted_at IS NULL',

            //Learning Moments / Evidence
            'evidence'          => 'SELECT count(*) FROM evidence WHERE deleted_at IS NULL',
            'evidence_comments' => 'SELECT count(*) FROM evidence JOIN discussion_thread_comment dtc on evidence.discussion_thread_id = dtc.discussion_thread_id',

            //Badges
            'badges'            => 'SELECT count(*) FROM badge WHERE deleted_at IS NULL',
            'badge_goals'       => 'SELECT count(*) FROM badge_user WHERE status = "goal" AND deleted_at IS NULL',
            'badge_achieved'       => 'SELECT count(*) FROM badge_user WHERE status = "achievement" AND deleted_at IS NULL',
        ];

        $result['instance_id'] = INSTANCE_ID;
        $result['school_code'] = $config->get('site_school_code');
        $result['type'] = $config->get('site_type');
        $result['version'] = $config->get('site_version');
        $result['plan'] = $config->get('site_plan');


        foreach ($queryList as $name => $query) {
            $statement = $db->executeQuery($query);
            $result[$name] = (int)$statement->fetchColumn(0);
        }

        echo json_encode($result);
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runapp();
