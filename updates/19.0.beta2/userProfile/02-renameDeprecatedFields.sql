ALTER TABLE user_profile 
    CHANGE guardian1Address     _guardian1Address     VARCHAR(255) CHARSET utf8mb4 NULL COMMENT 'deprecated in 19.0',
    CHANGE guardian1Suburb      _guardian1Suburb      VARCHAR(255) CHARSET utf8mb4 NULL COMMENT 'deprecated in 19.0',
    CHANGE guardian1Postcode    _guardian1Postcode    VARCHAR(255) CHARSET utf8mb4 NULL COMMENT 'deprecated in 19.0',
    CHANGE guardian1MailAddress _guardian1MailAddress VARCHAR(255) CHARSET utf8mb4 NULL COMMENT 'deprecated in 19.0',
    CHANGE guardian2Address     _guardian2Address     VARCHAR(255) CHARSET utf8mb4 NULL COMMENT 'deprecated in 19.0',
    CHANGE guardian2Suburb      _guardian2Suburb      VARCHAR(255) CHARSET utf8mb4 NULL COMMENT 'deprecated in 19.0',
    CHANGE guardian2Postcode    _guardian2Postcode    VARCHAR(255) CHARSET utf8mb4 NULL COMMENT 'deprecated in 19.0',
    CHANGE guardian2MailAddress _guardian2MailAddress VARCHAR(255) CHARSET utf8mb4 NULL COMMENT 'deprecated in 19.0';
