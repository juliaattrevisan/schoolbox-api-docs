-- There's no longer a need to have a return ID on rubric_descriptor_mark,
-- it's now associated through its rubric_group_mark.
ALTER TABLE `rubric_descriptor_mark`
    DROP FOREIGN KEY `fk_rubric_mark_return_id`;
ALTER TABLE `rubric_descriptor_mark`
    CHANGE COLUMN `return_id` `_return_id` INT(11) UNSIGNED NULL DEFAULT NULL;

-- Don't re-apply FK to this now deprecated field
# ALTER TABLE `rubric_descriptor_mark`
#     ADD CONSTRAINT `fk_rubric_mark_return_id`
#     FOREIGN KEY (`_return_id`)
#     REFERENCES `submission_return` (`id`)
#         ON DELETE NO ACTION
#         ON UPDATE NO ACTION;
