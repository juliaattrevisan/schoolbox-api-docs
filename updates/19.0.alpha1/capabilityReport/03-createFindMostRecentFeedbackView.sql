-- Specific views for capability and indicator marks only.
# CREATE OR REPLACE VIEW v_most_recent_feedback AS


SELECT
    'submission_return' AS feedback_table,
    sr1.id              AS feedback_id,
    sb.assessment_id,
    sr1.owner,
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
WHERE
    sr1.published_at IS NOT NULL
AND
    sr2.id IS NULL
