/*
 * Add foreign keys linking to storage.hash to the following columns:
 *  - sis_user_profile.portrait_hash
 *  - sportsfixtures_import_errors.hash
 *  - ups_log.hash
 */

UPDATE sis_user_profile
SET portrait_hash = NULL
WHERE portrait_hash IS NOT NULL
AND NOT EXISTS (
    SELECT *
    FROM storage
    WHERE hash = sis_user_profile.portrait_hash
);
ALTER TABLE sis_user_profile MODIFY portrait_hash varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci NULL;
ALTER TABLE sis_user_profile ADD CONSTRAINT sis_user_profile_storage_hash_fk FOREIGN KEY (portrait_hash) REFERENCES storage (hash);

UPDATE sportsfixtures_import_errors
SET hash = NULL
WHERE has IS NOT NULL
AND NOT EXISTS (
    SELECT *
    FROM storage
    WHERE hash = sportsfixtures_import_errors.hash
);
ALTER TABLE sportsfixtures_import_errors MODIFY hash varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci NULL;
ALTER TABLE sportsfixtures_import_errors ADD CONSTRAINT sportsfixtures_import_errors_storage_hash_fk FOREIGN KEY (hash) REFERENCES storage (hash);

UPDATE ups_log
SET hash = NULL
WHERE hash IS NOT NULL
AND NOT EXISTS (
    SELECT *
    FROM storage
    WHERE hash = ups_log.hash
);
ALTER TABLE ups_log MODIFY hash varchar(100) CHARACTER SET ascii COLLATE ascii_general_ci NULL;
ALTER TABLE ups_log ADD CONSTRAINT ups_log_storage_hash_fk FOREIGN KEY (hash) REFERENCES storage (hash);
