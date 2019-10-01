-- remove timestamps from join table
DELETE FROM pastoral_tags WHERE deleted_at IS NOT NULL;
ALTER TABLE pastoral_tags DROP COLUMN created_at;
ALTER TABLE pastoral_tags DROP COLUMN deleted_at;
-- delete tags that are not in use
UPDATE lu_pastoral_tag
  SET    `deleted_at` = NOW()
  WHERE  lu_pastoral_tag.`id` NOT IN (SELECT `tag_id` FROM `pastoral_tags`)
    AND  lu_pastoral_tag.deleted_at IS NULL;
