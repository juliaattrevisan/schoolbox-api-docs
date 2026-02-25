UPDATE wiki SET deleted_at = NULL WHERE deleted = 'false';
UPDATE wiki SET deleted_at = NOW() WHERE deleted = 'true';
