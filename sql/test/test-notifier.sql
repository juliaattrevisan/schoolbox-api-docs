-- Set up the test notifier.
DROP TABLE IF EXISTS `test`;
CREATE TABLE `test` (
    `storage_id` INT          NOT NULL AUTO_INCREMENT,
    `filename`   VARCHAR(250) NULL,
    `is_viewed`  BOOL         NOT NULL DEFAULT FALSE,
    `file`       BLOB         NULL,
    `parent`     INT          NULL,
    PRIMARY KEY (`storage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
