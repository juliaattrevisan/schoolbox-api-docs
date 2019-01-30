-- Delete usages of capabilities/indicators that are themselves deleted.

DELETE rgu FROM rubric_group_usage rgu
INNER JOIN rubric_group rg on rgu.rubric_group_id = rg.id
WHERE rg.deleted_at IS NOT NULL;

DELETE rcu FROM rubric_criterion_usage rcu
INNER JOIN rubric_criterion rc on rcu.criterion_id = rc.id
WHERE rc.deleted_at IS NOT NULL;
