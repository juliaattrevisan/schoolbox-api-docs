-- Create user_language table to store the user's languages
CREATE TABLE user_language
(
    user_id     INT(4) unsigned NOT NULL,
    language    int(11)    NOT NULL,
    CONSTRAINT fk_user_language_user_id
        FOREIGN KEY (user_id) REFERENCES user (id)
);

CREATE INDEX idx_user_language_user_id
    ON user_language (user_id);

