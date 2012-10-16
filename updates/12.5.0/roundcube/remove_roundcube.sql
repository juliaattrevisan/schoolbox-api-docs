DROP TABLE IF EXISTS `rc_cache`;
DROP TABLE IF EXISTS `rc_contactgroupmembers`;
DROP TABLE IF EXISTS `rc_contactgroups`;
DROP TABLE IF EXISTS `rc_contacts`;
DROP TABLE IF EXISTS `rc_identities`;
DROP TABLE IF EXISTS `rc_messages`;
DROP TABLE IF EXISTS `rc_session`;
DROP TABLE IF EXISTS `rc_users`;

DELETE FROM config WHERE name = 'mail_type';
DELETE FROM config WHERE name = 'mail_host';
DELETE FROM config WHERE name = 'mail_host_port';