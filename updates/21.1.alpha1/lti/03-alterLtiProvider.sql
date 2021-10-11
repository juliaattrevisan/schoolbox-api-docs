-- ADD LTI 1.3 fields
ALTER TABLE lti_provider
    ADD lti_version ENUM ('1.1', '1.3') NULL AFTER name,
    ADD deployment_id VARCHAR(36) CHARSET 'ascii' NULL AFTER lti_version,
    ADD audience VARCHAR(2048) NULL AFTER deployment_id,
    ADD oidc_initiation_url VARCHAR(2048) NULL AFTER audience,
    ADD launch_url VARCHAR(2048) NULL AFTER oidc_initiation_url,
    ADD deep_linking_url VARCHAR(2048) NULL AFTER launch_url,
    ADD jwks_url VARCHAR(2048) NULL AFTER deep_linking_url,
    ADD public_key TEXT NULL AFTER jwks_url;

-- All existing LTI providers are 1.1
UPDATE lti_provider SET lti_version = '1.1', deployment_id = UUID();
ALTER TABLE lti_provider
    MODIFY lti_version ENUM ('1.1', '1.3') NOT NULL,
    MODIFY deployment_id VARCHAR(36) CHARSET 'ascii' NOT NULL;
