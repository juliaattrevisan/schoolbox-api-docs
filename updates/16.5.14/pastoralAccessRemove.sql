DELETE pastoral_access
FROM pastoral_access
LEFT JOIN groups ON groups.id = pastoral_access.role_id
LEFT JOIN `user` ON `user`.id = pastoral_access.user_id
WHERE `user`.id IS NULL
AND groups.id IS NULL;