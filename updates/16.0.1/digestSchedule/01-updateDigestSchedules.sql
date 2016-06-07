-- Weekly digest constant - Friday, 11am
UPDATE groups
SET digest_schedule = '0 11 * * 5'
WHERE digest_schedule = '1';

-- Daily digest constant - every weekday, 6am
UPDATE groups
SET digest_schedule = '0 6 * * 1-5'
WHERE digest_schedule = '2';
