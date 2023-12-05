-- Reset duration to 0 for Unlimited, rather than -1
UPDATE `assess`
SET duration = 0
WHERE duration = -1;
