-- This table is to hold onetime key for SEB access
CREATE TABLE
    seb_onetime
(
    onetime_key VARCHAR(256) NOT NULL,
    config_hash VARCHAR(256) NOT NULL,
    created_at  DATETIME     NOT NULL
);
