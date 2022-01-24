-- Create relational table
CREATE TABLE user_jwt_id
(
    jwt_id  VARCHAR(36) CHARACTER SET ascii NOT NULL PRIMARY KEY UNIQUE,
    user_id INT(4) UNSIGNED                 NOT NULL,
    expiry  DATETIME,
    deleted_at DATETIME,
    CONSTRAINT fk_user_jwt_id_user_id FOREIGN KEY (user_id) REFERENCES user (id)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;
