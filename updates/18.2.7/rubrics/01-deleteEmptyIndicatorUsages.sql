-- find all indicator usages without any descriptors for that due work, and delete them
DELETE    rcu
FROM      rubric_criterion_usage rcu
LEFT JOIN rubric_level           rl ON rcu.submission_box_id = rl.box_id
LEFT JOIN rubric_descriptor      rd ON rl.id = rd.level_id AND rcu.criterion_id = rd.criterion_id
WHERE     rd.id IS NULL;
