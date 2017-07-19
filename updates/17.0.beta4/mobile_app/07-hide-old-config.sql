/* hide the old config options (don't delete right away, we might need them) */
UPDATE `config`
SET hidden = 'true'
WHERE `name` LIKE '%push_gcm%' OR `name` LIKE '%push_apns%';
