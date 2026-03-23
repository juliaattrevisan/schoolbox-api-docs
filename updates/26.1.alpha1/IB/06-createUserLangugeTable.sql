-- Create user_language table to store the user's languages
CREATE TABLE user_language
(
    id          INT(11) auto_increment PRIMARY KEY,
    user_id     INT(4) unsigned NOT NULL,
    language    VARCHAR(255)    NOT NULL,
    CONSTRAINT fk_user_language_user_id
        FOREIGN KEY (user_id) REFERENCES user (id)
);

CREATE INDEX idx_user_language_user_id
    ON user_language (user_id);

