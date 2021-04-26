CREATE TABLE instance_registry
(
    id INT(11) NOT NULL PRIMARY KEY UNIQUE AUTO_INCREMENT,
    instance_id VARCHAR(250) NOT NULL UNIQUE,
    domain_name VARCHAR(250) NOT NULL,
    jwt_public_key TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL DEFAULT NULL
);
