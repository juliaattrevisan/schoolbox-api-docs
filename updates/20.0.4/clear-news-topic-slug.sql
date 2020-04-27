-- Slug for deleted news topic and empty topic should be null
UPDATE newsTopic SET slug = NULL WHERE deleted_at IS NOT NULL OR `name` IS NULL OR `name` = '';
