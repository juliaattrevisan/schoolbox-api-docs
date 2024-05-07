UPDATE `curriculum_node`
SET `code` = ''
WHERE `code` IS NULL;

ALTER TABLE `curriculum_node`
    MODIFY `code` VARCHAR(64) NOT NULL DEFAULT '';
