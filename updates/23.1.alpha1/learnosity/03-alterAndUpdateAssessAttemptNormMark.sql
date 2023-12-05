-- assess_attempt.mark is now the human readable mark, I guess?
-- We never really had to store the values that *inform* the norm- and human-readable
-- marks before Learnosity...this is kind of synonymous with submission_return's usage

-- make a backup, clone the values, then alter the original
ALTER TABLE `assess_attempt`
    ADD COLUMN `_mark` DECIMAL(15,2) NULL DEFAULT NULL AFTER `deleted_at`;

UPDATE assess_attempt
SET _mark = mark;

-- represent norm_mark for old attempts:
-- ensure that it's between 0 and 100
UPDATE assess_attempt
INNER JOIN assess ON assess_attempt.assess_id = assess.assess_id
SET norm_mark = FORMAT(
    GREATEST(
        0,
        LEAST(100, 100 * mark / max_mark)
    ),
    11
)
WHERE assess.availability = 'assess';
