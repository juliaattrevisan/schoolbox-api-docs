-- TODO: there's more to deprecate from submission_box, for now this is just rubric_mark_range

ALTER TABLE `submission_box` RENAME COLUMN `rubric_mark_range` TO `_rubric_mark_range`;
