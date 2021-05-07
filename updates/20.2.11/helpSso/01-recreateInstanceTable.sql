-- If the table wasn't created:
CREATE TABLE IF NOT EXISTS instance_registry
(
    id INT(11) NOT NULL PRIMARY KEY UNIQUE AUTO_INCREMENT,
    instance_id VARCHAR(250) CHARACTER SET ascii NOT NULL UNIQUE,
    site_school_code VARCHAR(250) NOT NULL,
    domain_name VARCHAR(250) NOT NULL,
    jwt_public_key TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL DEFAULT NULL
);

-- If it was, set its instance_id back to ascii anyway
ALTER TABLE instance_registry MODIFY instance_id VARCHAR(250) CHARACTER SET ascii NOT NULL;
