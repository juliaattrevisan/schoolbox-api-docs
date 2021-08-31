/**
 * A view for finding assessment feedback.
 *
 * Differs from v_most_recent_feedback by not attempting to determine which is
 * the most recent (and thus including no GROUP BY): this makes the view
 * merge-optimizable.
 *
 * https://mariadb.com/kb/en/derived-table-merge-optimization/#factsheet
 * suggests that queries on views which contain GROUP BY clauses can't be
 * rewritten: they must use temporary tables.
 */
CREATE OR REPLACE VIEW v_assessment_feedback AS

(
    -- Due work feedback
    SELECT sb.assessment_id,
           'submission_return' AS feedback_table,
           sr.id               AS feedback_id,
           sr.owner            AS student_id,
           sr.review_route,
           sr.published_at
    FROM submission_return sr
    INNER JOIN submission_box sb ON sr.box_id = sb.id

    /**
     * Filter out:
     * * deleted feedback
     */
    WHERE sr.deleted_at IS NULL
)

UNION
(
    -- Quiz feedback
    SELECT ass.assessment_id,
           'assess_attempt' AS feedback_table,
           aa.attempt_id    AS feedback_id,
           aa.user_id       AS student_id,
           'instructor'     AS review_route,
           aa.reviewed_at   AS published_at
    FROM assess_attempt aa
    INNER JOIN assess ass ON aa.assess_id = ass.assess_id

    /**
     * Filter out:
     * * any deleted attempts
     * * any unreviewed attempts
     * * any attempts for non-assessment quizzes (i.e. surveys)
     */
    WHERE aa.deleted_at     IS NULL
    AND   aa.reviewed_at    IS NOT NULL
    AND   ass.assessment_id IS NOT NULL
)

UNION
(
    -- Task feedback
    SELECT t.assessment_id,
           'task_completion'  AS feedback_table,
           tc.id              AS feedback_id,
           tc.student_id,
           'instructor'       AS review_route,
           tc.completion_date AS published_at
    FROM task_completion tc
    INNER JOIN task t ON tc.task_id = t.id

    /**
     * Filter out:
     * * any deleted completions
     * * any completions with no completion date
     * * any completions not marked by teacher (completion_status=3)
     *   (Tasks marked as completed by a student could be considered a feedback
          with review_route='self-grading'?)
     */
    WHERE tc.deleted_at        IS NULL
    AND   tc.completion_date   IS NOT NULL
    AND   tc.completion_status = 3
)
ORDER BY assessment_id ASC, student_id ASC;
