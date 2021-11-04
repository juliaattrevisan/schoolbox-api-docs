ALTER TABLE mobile_app
     ADD deleted_at DATETIME NULL;

ALTER TABLE mobile_app_registry
    ADD deleted_at DATETIME NULL;

DROP TABLE IF EXISTS mobile_app_registry_backup;
