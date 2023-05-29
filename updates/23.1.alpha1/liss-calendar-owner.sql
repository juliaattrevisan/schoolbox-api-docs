-- LISS data moved to suds; no longer requires an explicit event owner

DELETE FROM `config` WHERE `name` = 'external_liss_calendar_owner'
LIMIT 1;
