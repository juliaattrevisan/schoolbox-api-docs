-- assess_attempt.mark is now the human readable mark, I guess?
-- We never really had to store the values that *inform* the norm- and human-readable
-- marks before Learnosity...this is kind of synonymous with submission_return's usage

-- make a backup, clone the values, then alter the original
ALTER TABLE `assess_attempt`
    ADD COLUMN `_mark` DECIMAL(15,2) NULL DEFAULT NULL AFTER `deleted_at`;

UPDATE assess_attempt
SET _mark = mark;
