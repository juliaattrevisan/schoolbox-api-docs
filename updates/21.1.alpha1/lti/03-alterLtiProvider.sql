-- ADD LTI 1.3 fields
ALTER TABLE lti_provider
    ADD lti_version ENUM ('1.1', '1.3') NULL AFTER name,
    ADD audience VARCHAR(2048) NULL AFTER lti_version,
    ADD oidc_initiation_url VARCHAR(2048) NULL AFTER audience,
    ADD launch_url VARCHAR(2048) NULL AFTER oidc_initiation_url,
    ADD deep_linking_url VARCHAR(2048) NULL AFTER launch_url;

-- All existing LTI providers are 1.1
UPDATE lti_provider SET lti_version = '1.1';
ALTER TABLE lti_provider
    MODIFY lti_version ENUM ('1.1', '1.3') NOT NULL;
