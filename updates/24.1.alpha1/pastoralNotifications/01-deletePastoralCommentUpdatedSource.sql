-- Delete pastoral comment update msg_reroute entries
-- (these are the user overrides for notification delivery mechanisms)
DELETE mr
FROM msg_reroute mr
INNER JOIN msg_source    ms ON mr.msg_source_id = ms.id
INNER JOIN msg_namespace mn ON ms.namespace = mn.id
WHERE mn.slug = 'pastoral_comment'
AND   ms.key IN ('updated', 'updatedRelated');

-- Delete pastoral comment update msg_route_perm entries
-- (these are the per-role defaults for notification delivery mechanisms)
DELETE mrp
FROM msg_route_perm mrp
INNER JOIN msg_source    ms ON mrp.msg_source_id = ms.id
INNER JOIN msg_namespace mn ON ms.namespace = mn.id
WHERE mn.slug = 'pastoral_comment'
AND   ms.key IN ('updated', 'updatedRelated');

-- Delete pastoral comment update msg_source entries
-- (these are the notification sources themselves)
DELETE ms
FROM msg_source ms
INNER JOIN msg_namespace mn ON ms.namespace = mn.id
WHERE mn.slug = 'pastoral_comment'
AND   ms.key IN ('updated', 'updatedRelated')
