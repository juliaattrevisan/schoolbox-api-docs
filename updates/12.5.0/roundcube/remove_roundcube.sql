DROP TABLE `rc_cache`;
DROP TABLE `rc_contactgroupmembers`;
DROP TABLE `rc_contactgroups`;
DROP TABLE `rc_contacts`;
DROP TABLE `rc_identities`;
DROP TABLE `rc_messages`;
DROP TABLE `rc_session`;
DROP TABLE `rc_users`;

DELETE FROM config WHERE name = 'mail_type';
DELETE FROM config WHERE name = 'mail_host';
DELETE FROM config WHERE name = 'mail_host_port';