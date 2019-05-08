-- find all capability usages whose indicators aren't used for that due work, and delete them
DELETE     rgu
FROM       rubric_group_usage     rgu
INNER JOIN rubric_group           rg  ON rgu.rubric_group_id = rg.id
INNER JOIN rubric_criterion       rc  ON rg.id = rc.rubric_group_id
LEFT  JOIN rubric_criterion_usage rcu ON rc.id = rcu.criterion_id
WHERE      rcu.criterion_id IS NULL;
