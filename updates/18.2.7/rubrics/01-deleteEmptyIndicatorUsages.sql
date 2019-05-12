-- find all indicator usages without any descriptors for that due work, and delete them
DELETE rcu FROM rubric_criterion_usage rcu
WHERE (
    SELECT     COUNT(*)
    FROM       rubric_descriptor rd
    INNER JOIN rubric_level rl ON rd.level_id = rl.id
    WHERE      rl.box_id       = rcu.submission_box_id
    AND        rd.criterion_id = rcu.criterion_id
    AND        rd.deleted_at IS NULL
) = 0;
