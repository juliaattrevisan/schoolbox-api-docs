/**
 * The package install process for badges has mistakenly associated badges with
 * no curriculum code with the first node which has no code.
 *
 * It's safe to delete these associations, because they cannot be created via
 * the UI via normal usage.
 */
DELETE cb FROM curriculum_badge cb
INNER JOIN curriculum_node cn ON cb.curriculum_node_id = cn.id
WHERE COALESCE(cn.code, '') = '';
