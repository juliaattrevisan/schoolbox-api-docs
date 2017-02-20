-- It is now an invalid state for a submission_box not to indicate its markType.
-- If it is NULL, set it explicitly to the 'None' mark scheme.
UPDATE submission_box
SET mark_type = (SELECT mark_type_id FROM submission_mark_type WHERE slug = 'none')
WHERE mark_type IS NULL;

-- Ensure it cannot get into this state in the future.
ALTER TABLE `submission_box` CHANGE `mark_type` `mark_type` INT(11) NOT NULL;

-- And add a foreign key to prevent invalid input
ALTER TABLE `submission_box`
  ADD CONSTRAINT `fk_submission_box_mark_type`
  FOREIGN KEY (`mark_type`)
  REFERENCES `submission_mark_type` (`mark_type_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION;
