-- switch off "Use Rubric" for all due works without any capability usages
UPDATE    submission_box     sb
LEFT JOIN rubric_group_usage rgu ON sb.id = rgu.submission_box_id
SET       sb.use_rubric = FALSE
WHERE     rgu.rubric_group_id IS NULL;
