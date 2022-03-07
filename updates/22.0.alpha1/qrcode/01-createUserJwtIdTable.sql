-- Create relational table
CREATE TABLE user_jwt_id
(
    jwt_id     VARCHAR(64) CHARACTER SET ascii NOT NULL PRIMARY KEY UNIQUE,
    user_id    INT(4) UNSIGNED                 NOT NULL,
    expiry     DATETIME,
    created_at DATETIME,
    deleted_at DATETIME,
    created_by INT(4) UNSIGNED,
    deleted_by INT(4) UNSIGNED,
    CONSTRAINT fk_user_jwt_id_user_id FOREIGN KEY (user_id) REFERENCES user (id),
    CONSTRAINT fk_user_jwt_id_created_by FOREIGN KEY (created_by) REFERENCES user (id),
    CONSTRAINT fk_user_jwt_id_deleted_by FOREIGN KEY (deleted_by) REFERENCES user (id)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;
