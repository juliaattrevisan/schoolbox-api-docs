UPDATE forumPost set deleted_at = NOW() WHERE deleted = 'true';
UPDATE forumThread set deleted_at = NOW() WHERE deleted = 'true';
