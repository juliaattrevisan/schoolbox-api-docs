CREATE OR REPLACE VIEW v_most_recent_feedback AS

(
    SELECT sb.assessment_id    AS assessment_id,
           'submission_return' AS feedback_table,
           max(sr1.id)         AS feedback_id,
           sr1.owner           AS student_id,
           sr1.review_route    AS review_route,
           sr1.published_at    AS published_at
    FROM submission_return sr1
             JOIN submission_box sb ON sr1.box_id = sb.id
             LEFT JOIN submission_return sr2 ON sr1.box_id = sr2.box_id
        AND sr1.owner = sr2.owner
        AND sr1.review_route = sr2.review_route
        AND sr2.published_at IS NOT NULL
        AND sr2.deleted_at IS NULL
        AND
                                                sr1.published_at < sr2.published_at
    WHERE sr1.published_at IS NOT NULL
      AND sr1.deleted_at IS NULL
      AND sr2.id IS NULL
    GROUP BY sb.assessment_id, sr1.owner, sr1.review_route, sr1.published_at
)

UNION
(
    SELECT ass.assessment_id   AS assessment_id,
           'assess_attempt'    AS feedback_table,
           max(aa1.attempt_id) AS feedback_id,
           aa1.user_id         AS student_id,
           'instructor'        AS review_route,
           aa1.reviewed_at     AS published_at
    FROM assess_attempt aa1
             JOIN assess ass ON aa1.assess_id = ass.assess_id
             LEFT JOIN assess_attempt aa2 ON aa1.assess_id = aa2.assess_id
        AND aa1.user_id = aa2.user_id
        AND aa2.reviewed_at IS NOT NULL
        AND aa2.deleted_at IS NULL
        AND aa1.reviewed_at < aa2.reviewed_at
    WHERE aa1.reviewed_at IS NOT NULL
      AND aa1.deleted_at IS NULL
      AND aa2.attempt_id IS NULL
      AND ass.assessment_id IS NOT NULL
    GROUP BY ass.assessment_id, aa1.user_id, aa1.reviewed_at
)

UNION
(
    SELECT t.assessment_id     AS assessment_id,
           'task_completion'   AS feedback_table,
           max(tc1.id)         AS feedback_id,
           tc1.student_id      AS student_id,
           'instructor'        AS review_route,
           tc1.completion_date AS published_at
    FROM task_completion tc1
             JOIN task t ON tc1.task_id = t.id
             LEFT JOIN task_completion tc2 ON tc1.task_id = tc2.task_id
        AND tc1.student_id = tc2.student_id
        AND tc1.completion_status = tc2.completion_status
        AND tc2.deleted_at IS NULL
        AND tc1.completion_date < tc2.completion_date
    WHERE tc1.completion_date IS NOT NULL
      AND tc1.deleted_at IS NULL
      AND tc2.id IS NULL
      AND tc1.completion_status = 3
    GROUP BY t.assessment_id, tc1.student_id, tc1.completion_date
)

UNION
(
    SELECT la.assessment_id       AS assessment_id,
           'lti_assessment_score' AS feedback_table,
           max(sc1.id)            AS feedback_id,
           sc1.student_id,
           'instructor'           AS review_route,
           sc1.created_at         AS published_at
    FROM lti_assessment_score sc1
             JOIN lti_assessment la ON sc1.lti_assessment_id = la.id
             LEFT JOIN lti_assessment_score sc2 ON sc1.lti_assessment_id = sc2.lti_assessment_id
        AND sc1.student_id = sc2.student_id
        AND sc2.created_at IS NOT NULL
        AND sc2.deleted_at IS NULL
        AND sc1.created_at < sc2.created_at
    WHERE sc1.created_at IS NOT NULL
      AND sc1.deleted_at IS NULL
      AND sc1.is_feedback = 1
      AND sc2.id IS NULL
    GROUP BY la.assessment_id, sc1.student_id, sc1.created_at
)
ORDER BY assessment_id, student_id
