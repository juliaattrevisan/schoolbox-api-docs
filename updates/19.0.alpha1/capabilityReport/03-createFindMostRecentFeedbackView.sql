-- Specific views for capability and indicator marks only.
# CREATE OR REPLACE VIEW v_most_recent_feedback AS

-- Due work attempt feedback
SELECT
    sb.assessment_id,
    'submission_return' AS feedback_table,
    MAX(sr1.id)         AS feedback_id,
    sr1.owner           AS student_id,
    sr1.review_route,
    sr1.published_at
FROM       submission_return sr1
INNER JOIN submission_box    sb  ON sr1.box_id = sb.id

-- Join onto any return for the same group (assessment/owner/review route)
-- that was published before this one.
LEFT  JOIN submission_return sr2 ON (
    sr1.box_id       = sr2.box_id
    AND
    sr1.owner        = sr2.owner
    AND
    sr1.review_route = sr2.review_route
    AND
    sr2.published_at IS NOT NULL
    AND
    sr1.published_at < sr2.published_at
)

-- Filter out any unpublished returns, and any which have an earlier published time.
WHERE sr1.published_at IS NOT NULL
AND   sr2.id           IS NULL

GROUP BY sb.assessment_id,
         sr1.owner,
         sr1.review_route,
         sr1.published_at


-- Quiz attempt feedback
UNION
SELECT
    ass.assessment_id,
    'assess_attempt'    AS feedback_table,
    MAX(aa1.attempt_id) AS feedback_id,
    aa1.user_id         AS student_id,
    'instructor'        AS review_route,
    aa1.reviewed_at     AS published_at
FROM       assess_attempt aa1
INNER JOIN assess         ass ON aa1.assess_id = ass.assess_id

-- Join onto any return for the same group (assessment/user)
-- that was reviewed before this one.
LEFT  JOIN assess_attempt aa2 ON (
    aa1.assess_id = aa2.assess_id
    AND
    aa1.user_id   = aa2.user_id
    AND
    aa2.reviewed_at IS NOT NULL
    AND
    aa1.reviewed_at < aa2.reviewed_at
)

-- Filter out any unreviewed attempts for non-assessment quizzes (i.e. surveys),
-- and any which have an earlier published time.
WHERE aa1.reviewed_at   IS NOT NULL
AND   aa2.attempt_id    IS NULL
AND   ass.assessment_id IS NOT NULL

GROUP BY ass.assessment_id,
         aa1.user_id,
         aa1.reviewed_at


ORDER BY assessment_id ASC, student_id ASC
;
