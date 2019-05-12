-- find all capability usages whose indicators aren't used for that due work, and delete them
DELETE rgu FROM rubric_group_usage rgu
WHERE (
    SELECT     COUNT(*)
    FROM       rubric_criterion_usage rcu
    INNER JOIN rubric_criterion       rc  ON rcu.criterion_id = rc.id
    WHERE      rcu.submission_box_id = rgu.submission_box_id
    AND        rc.rubric_group_id    = rgu.rubric_group_id
) = 0;
