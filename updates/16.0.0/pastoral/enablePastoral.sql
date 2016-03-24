-- Enable pastoral module

UPDATE `config` SET `value` = 1 WHERE `name` = 'external_pastoral';
UPDATE `config` SET `value` = 1 WHERE `name` = 'pastoral_enabled';