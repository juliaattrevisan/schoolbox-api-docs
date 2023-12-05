-- Reset duration to 0 for Unlimited, rather than -1
ALTER TABLE `assess`
    ADD COLUMN `_duration` INT NOT NULL DEFAULT 0 AFTER `duration`;

UPDATE `assess`
SET _duration = duration;

UPDATE `assess`
SET duration = 0
WHERE duration = -1;
