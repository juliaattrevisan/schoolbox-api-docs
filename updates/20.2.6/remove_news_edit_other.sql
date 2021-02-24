-- Remove obsolete config
DELETE FROM `config` WHERE `name` = 'news_edit_other';

-- Resequence
UPDATE `config`
SET sequence = sequence-1
WHERE module = 'comms'
  AND sequence > 3;