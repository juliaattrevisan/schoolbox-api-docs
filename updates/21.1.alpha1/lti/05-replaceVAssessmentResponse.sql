/**
 * A view for finding assessment responses.
 */
CREATE OR REPLACE VIEW v_assessment_response AS

(
    -- Due work responses
    SELECT sb.assessment_id,
           'student_submission' AS response_table,
           ss.id                AS response_id,
           ss.owner             AS student_id,
           ss.submitted_at
    FROM student_submission ss
             INNER JOIN submission_box sb ON ss.box_id = sb.id

        /**
         * Filter out:
         * * deleted responses
         */
    WHERE ss.deleted_at IS NULL
)

UNION
(
    -- Quiz responses (consider any attempt on a quiz a response)
    SELECT ass.assessment_id,
           'assess_attempt' AS response_table,
           aa.attempt_id    AS response_id,
           aa.user_id       AS student_id,
           aa.finish        AS submitted_at
    FROM assess_attempt aa
             INNER JOIN assess ass ON aa.assess_id = ass.assess_id

        /**
         * Filter out:
         * * any deleted attempts
         * * any attempts for non-assessment quizzes (i.e. surveys)
         */
    WHERE aa.deleted_at IS NULL
      AND ass.assessment_id IS NOT NULL
)

UNION
(
    -- Task responses (consider any completion a response)
    SELECT t.assessment_id,
           'task_completion'  AS response_table,
           tc.id              AS response_id,
           tc.student_id,
           tc.completion_date AS submitted_at
    FROM task_completion tc
             INNER JOIN task t ON tc.task_id = t.id

        /**
         * Filter out:
         * * any deleted completions
         * * any completions with no completion date
         * * any completions not marked by student (completion_status=2)
         */
    WHERE tc.deleted_at IS NULL
      AND tc.completion_date IS NOT NULL
      AND tc.completion_status = 2
)

UNION
(
    -- LTI Assessment responses
    SELECT la.assessment_id,
           'lts_assessment_score' AS response_table,
           sc.id                  AS response_id,
           sc.student_id,
           sc.created_at          AS submitted_at
    FROM lti_assessment_score sc
             INNER JOIN lti_assessment la ON sc.lti_assessment_id = la.id
        /**
         * Filter out:
         * * deleted responses
         */
    WHERE sc.deleted_at IS NULL
      AND sc.is_response = 1
)
ORDER BY assessment_id ASC, student_id ASC;
