/**
 * Attempt to deprecate `mobile_app_registry.provider` if it failed in
 * `updates/21.0.alpha1/digistorm/03-deprecateMobileAppRegistryProvider.sql`.
 */

DELIMITER $$

DROP PROCEDURE IF EXISTS upgrade_add_column $$

CREATE PROCEDURE upgrade_add_column()
BEGIN

    IF NOT EXISTS (
        (
            SELECT * FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND COLUMN_NAME = '_provider'
            AND TABLE_NAME = 'mobile_app_registry'
        )
    ) THEN

        /* the stuff to do */
        ALTER TABLE mobile_app_registry DROP PRIMARY KEY;
        ALTER TABLE mobile_app_registry ADD PRIMARY KEY (user_id, app_token(255), app_id);
        ALTER TABLE mobile_app_registry CHANGE provider _provider ENUM('APNS', 'GCM', 'Digistorm') CHARSET ASCII NOT NULL;

    END IF;

END $$

CALL upgrade_add_column() $$

DELIMITER ;

DROP PROCEDURE IF EXISTS upgrade_add_column;
