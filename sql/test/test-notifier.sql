-- Set up the test notifier.
DROP TABLE IF EXISTS `test`;
CREATE TABLE `test` (
    `storage_id` INT          NOT NULL AUTO_INCREMENT,
    `user_id`    INT          NOT NULL,
    `filename`   VARCHAR(250) NULL,
    `is_viewed`  BOOL         NOT NULL DEFAULT FALSE,
    `file`       BLOB         NULL,
    `parent`     INT          NULL,
    PRIMARY KEY (`storage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `msg_namespace` (slug, label) VALUES ('test', 'Test');
SELECT `id` INTO @namespaceId FROM msg_namespace WHERE slug='Test' LIMIT 1;

INSERT IGNORE INTO `msg_source` (namespace, `key`, role_type, description)
VALUES
    (@namespaceId, 'notified', 'student', 'Student Test'),
    (@namespaceId, 'notified', 'staff', 'Staff Test');

SELECT ms.id INTO @staffSourceId
FROM msg_source ms
INNER JOIN msg_namespace mn ON ms.namespace = mn.id
WHERE mn.slug='Test' AND ms.role_type = 'staff' LIMIT 1;

SELECT ms.id INTO @studentSourceId
FROM msg_source ms
INNER JOIN msg_namespace mn ON ms.namespace = mn.id
WHERE mn.slug='Test' AND ms.role_type = 'student' LIMIT 1;

INSERT IGNORE INTO `msg_route_perm` (msg_source_id, role_id, mask, role_default)
VALUES
    (@staffSourceId, 1, 'IN', 'IN'),
    (@staffSourceId, 2, 'IN', 'IN'),
    (@studentSourceId, 3, 'DI', 'DI'),
    (@studentSourceId, 5, 'DI', 'DI')
;
