-- change data type
ALTER TABLE `assess_attempt`
    CHANGE COLUMN `mark` `mark` VARCHAR(255) NULL DEFAULT NULL ;

-- represent total mark for historical marks
-- use this trim tactic to remove trailing zeroes (wild!)
-- https://stackoverflow.com/questions/7968531/remove-trailing-zeros-in-decimal-value-with-changing-length
UPDATE assess_attempt
INNER JOIN assess ON assess_attempt.assess_id = assess.assess_id
SET mark = CONCAT(trim(mark)+0, " / ", trim(max_mark)+0)
WHERE assess.availability = 'assess';
