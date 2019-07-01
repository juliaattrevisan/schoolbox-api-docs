-- Remove Humanedge option
UPDATE `config` SET `options`='synergetic:tass:denbigh:edumate:schoolbox:schoolpro:maze:pcschools:doublefirst:schooledge:isams' WHERE `name`='external_type';

DELETE FROM `config`
WHERE `name` IN (
    'external_humanedge_aplus_db_db',
    'external_humanedge_aplus_db_host',
    'external_humanedge_aplus_db_user',
    'external_humanedge_aplus_db_passwd'
);