-- rubric is now derived from the count of its rubric instances

ALTER TABLE `submission_box` RENAME COLUMN `use_rubric` TO `_use_rubric`;
