-- Not used by the mapper and I'm unable to find usage in code base. (DEVELOP ONLY).
-- It appears this column's functionality is now provided by `image_hash`.
ALTER TABLE `resource` DROP COLUMN `image_url`;