ALTER TABLE `allowedFileTypes`
  ADD COLUMN `deleted` BOOL DEFAULT 0  NOT NULL AFTER `max_file_size`;
