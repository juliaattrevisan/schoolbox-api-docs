DROP TABLE `rc_cache` IF EXISTS;
DROP TABLE `rc_contactgroupmembers` IF EXISTS;
DROP TABLE `rc_contactgroups` IF EXISTS;
DROP TABLE `rc_contacts` IF EXISTS;
DROP TABLE `rc_identities` IF EXISTS;
DROP TABLE `rc_messages` IF EXISTS;
DROP TABLE `rc_session` IF EXISTS;
DROP TABLE `rc_users` IF EXISTS;

DELETE FROM config WHERE name = 'mail_type';
DELETE FROM config WHERE name = 'mail_host';
DELETE FROM config WHERE name = 'mail_host_port';