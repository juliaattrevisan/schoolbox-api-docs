CREATE TABLE `kami_upload`
(
    `id`               int(11)                                                          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `kami_document_id` char(32) CHARACTER SET ascii COLLATE ascii_general_ci            DEFAULT NULL UNIQUE,
    `status`           enum ('queued','uploading','failed','processing','done','error') NOT NULL,
    `error_message`    varchar(2048)                                                    DEFAULT NULL,
    `kami_created_at`  datetime                                                         DEFAULT NULL,
    `created_at`       datetime                                                         NOT NULL,
    `updated_at`       datetime                                                         NOT NULL,
    `deleted_at`       datetime                                                         DEFAULT NULL
);
