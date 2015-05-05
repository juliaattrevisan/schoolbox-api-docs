ALTER TABLE `socialstream_post`
  ADD COLUMN `cache_hash` VARCHAR(40) NULL AFTER `mimetype`;
