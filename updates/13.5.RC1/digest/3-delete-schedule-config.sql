-- The digest config settings aren't needed anymore.
DELETE FROM `config` WHERE `name` IN ('digest_send_weekly', 'digest_send_daily');
