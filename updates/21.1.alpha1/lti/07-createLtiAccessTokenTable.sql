-- Table for oAuth access tokens for LTI
CREATE TABLE lti_access_token
(
    identifier      VARCHAR(250) NOT NULL PRIMARY KEY,
    lti_provider_id INT          NOT NULL,
    user_id         INT UNSIGNED NULL,
    expires_at      DATETIME     NOT NULL,
    scopes          JSON         NULL,
    CONSTRAINT lti_access_token_lti_provider_id_fk
        FOREIGN KEY (lti_provider_id) REFERENCES lti_provider (id),
    CONSTRAINT lti_access_token_user_fk
        FOREIGN KEY (user_id) REFERENCES user (id)
);
