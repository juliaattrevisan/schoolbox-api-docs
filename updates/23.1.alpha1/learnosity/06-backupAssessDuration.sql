ALTER TABLE `assess`
    ADD COLUMN `_duration` INT NOT NULL DEFAULT 0 AFTER `duration`;

UPDATE `assess`
SET _duration = duration;
