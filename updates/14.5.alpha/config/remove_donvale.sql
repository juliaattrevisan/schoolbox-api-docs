DELETE FROM `config` WHERE `name` LIKE 'donvale_%';

UPDATE `config` SET hidden = 'true' WHERE `name` like 'external_sync%';
