-- Make config name consistent with all the other ones
UPDATE `config`
SET title = 'Enable Kami'
WHERE name = 'kami_enabled';