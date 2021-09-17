-- Total mark capability marks: just set to the same as value
UPDATE     rubric_capability_mark  rcm
INNER JOIN submission_return       sr  ON rcm.return_id = sr.id
INNER JOIN rubric_capability_usage rcu ON rcm.rubric_capability_id = rcu.rubric_capability_id AND sr.box_id = rcu.submission_box_id
INNER JOIN submission_box          sb  ON sr.box_id = sb.id
INNER JOIN submission_mark_type    smt ON sb.rubric_capability_mark_type_id = smt.mark_type_id

-- Remove trailing zeros from numerator and denominator of stored mark:
-- ROUND value to 2dp, then TRIM to cast to string, then add 0 to remove trailing zeros
SET rcm.human_readable_value = CONCAT(
    TRIM(ROUND(rcm.value, 2)) + 0,
    ' / ',
    TRIM(ROUND(rcu.max_value, 2)) + 0
)

WHERE smt.slug = 'raw';
