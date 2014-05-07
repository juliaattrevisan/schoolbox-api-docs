INSERT INTO `msg_namespace` VALUES (9, 'eotcmetadatastatus', 'EOTC Status Update');

INSERT INTO `msg_source`
  (`namespace`, `key`, `role_type`, `description`)
VALUES
  (9, 'changed', 'staff', 'an EOTC Event status changes');