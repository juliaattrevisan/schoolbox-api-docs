-- The rewrites these queries to use a subquery, rather than a LEFT JOIN, to minimise intermediate result sets
CREATE OR REPLACE VIEW v_most_recent_feedback AS

    -- latest feedback for student,assessment,review_route,MAX(published_at)
    SELECT
        sb.assessment_id    AS assessment_id,
        'submission_return' AS feedback_table,
        MAX(sr1.id)         AS feedback_id,
        sr1.owner           AS student_id,
        sr1.review_route    AS review_route,
        sr1.published_at    AS published_at
    FROM submission_return sr1
    JOIN (
        -- Most recent published feedback date (not ID) for assessment/student/review_route
        -- Note that there may multiple feedbacks for a given assessment/student/review_route/published_date,
        -- will need to select one in the outer query
        SELECT box_id, owner, review_route, MAX(published_at) AS max_published_at
        FROM submission_return
        WHERE deleted_at IS NULL AND published_at IS NOT NULL
        GROUP BY box_id, owner, review_route
    ) t1 ON t1.box_id = sr1.box_id AND t1.owner = sr1.owner AND t1.review_route = sr1.review_route AND sr1.published_at = t1.max_published_at
    JOIN submission_box sb on sr1.box_id = sb.id
    GROUP BY assessment_id, sr1.owner, sr1.review_route

UNION

    -- latest attempt for student,assessment,MAX(reviewed_at)
    SELECT
        a.assessment_id     AS assessment_id,
        'assess_attempt'    AS feedback_table,
        MAX(aa1.attempt_id) AS feedback_id,
        aa1.user_id         AS student_id,
        'instructor'        AS review_route,
        aa1.reviewed_at     AS published_at
    FROM assess_attempt aa1
    JOIN (
        -- latest reviewed_at date for quiz,student
        -- Note that in theory there may be multiple attempts for a quiz/student with same reviewed_date
        -- Need to select one in outer query
        SELECT assess_id, user_id, MAX(reviewed_at) AS max_reviewed_at
        FROM assess_attempt
        WHERE reviewed_at IS NOT NULL AND deleted_at IS NULL
        GROUP BY assess_id, user_id
    ) t1 ON t1.assess_id = aa1.assess_id AND t1.user_id = aa1.user_id AND aa1.reviewed_at = t1.max_reviewed_at
    JOIN assess a on aa1.assess_id = a.assess_id
    GROUP BY a.assessment_id, aa1.user_id

UNION

    -- latest completion for quiz/student/MAX(completion_date)
    SELECT
        t.assessment_id    AS assessment_id,
        'task_completion'  AS feedback_table,
        MAX(tc.id)         AS feedback_id,
        tc.student_id      AS student_id,
        'instructor'       AS review_route,
        tc.completion_date AS published_at
    FROM task_completion tc
    JOIN (
        -- latest completion_date for quiz/student
        SELECT task_id, student_id, MAX(completion_date) AS max_completion_date
        FROM task_completion
        WHERE completion_status = 3 AND deleted_at IS NULL
        GROUP BY task_id, student_id
    ) t1 ON tc.task_id = t1.task_id AND tc.student_id = t1.student_id AND tc.completion_date = t1.max_completion_date
    JOIN task t ON t.id = tc.task_id
    GROUP BY t.assessment_id, tc.student_id

UNION

    -- latest LTI assessment score for assessment/student/MAX(created_at)
    SELECT
        la.assessment_id       AS assessment_id,
        'lti_assessment_score' AS feedback_table,
        max(las.id)            AS feedback_id,
        las.student_id,
        'instructor'           AS review_route,
        las.created_at         AS published_at
    FROM lti_assessment_score las
    JOIN (
        -- latest created_at date for assessment/student
        SELECT lti_assessment_id, student_id, MAX(created_at) AS max_created_at
        FROM lti_assessment_score
        WHERE deleted_at IS NULL AND is_feedback = 1
    ) t1 ON las.lti_assessment_id = t1.lti_assessment_id AND las.student_id = t1.student_id AND las.created_at = t1.max_created_at
    JOIN lti_assessment la on las.lti_assessment_id = la.id
    GROUP BY la.assessment_id, las.student_id;






