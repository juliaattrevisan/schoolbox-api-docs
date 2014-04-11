/* SB-4437 Securit config tab cleanup */
INSERT INTO `lu_config_type` VALUES(0,'LDAP');
INSERT INTO `lu_config_type` VALUES(0,'Proxy');

UPDATE `config` SET `type_id`=(SELECT `id` FROM `lu_config_type` WHERE `type`='LDAP') WHERE `name` LIKE 'ldap_%';
UPDATE `config` SET `type_id`=(SELECT `id` FROM `lu_config_type` WHERE `type`='Proxy') WHERE `name` LIKE 'proxy_%';
