-- Create user_nationality table to store the user's nationality
CREATE TABLE user_nationality
(
    user_id     INT(4) unsigned NOT NULL,
    nationality int(11)    NOT NULL,
    CONSTRAINT fk_user_nationality_user_id
        FOREIGN KEY (user_id) REFERENCES user (id)
);

CREATE INDEX idx_user_nationality_user_id
    ON user_nationality (user_id);
