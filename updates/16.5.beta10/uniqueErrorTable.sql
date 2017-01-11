ALTER IGNORE TABLE `error`
  CHANGE COLUMN `id` `id` INT(3) UNSIGNED NULL DEFAULT NULL,
  DROP INDEX `id`,
  ADD UNIQUE INDEX `id` (`id` ASC);

-- Error #184 (a likely duplicate) should relate to accepting online submissions at all.
REPLACE INTO `error` (`id`, `message`) VALUES ('184', 'This assessment does not accept online submissions.');
