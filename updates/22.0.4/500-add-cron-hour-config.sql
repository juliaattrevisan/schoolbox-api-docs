# Migrate UPS run times from Infra (puppet yaml file) to Product (config table)

# noinspection SqlCaseVsIf
INSERT INTO `config` (
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `required`,
    `module`,
    `sequence`
) VALUES (
    'ups_cron_run_time',
    -- These times have been parsed from puppet YAML files.
    (SELECT * FROM
        (SELECT
             CASE
                -- staging instances
                WHEN value = 'stg' THEN (
                    SELECT * FROM (
                        SELECT CASE
                            WHEN value = 'canbggs' THEN '04:00'
                            ELSE '22:00'
                        END
                        FROM `config` WHERE name = 'site_school_code') t2
                    )
                -- production instances
                WHEN value = 'prod' THEN (
                    SELECT * FROM (
                        SELECT CASE
                            WHEN value = 'fgs'     THEN '15:00'
                            WHEN value = 'lgs'     THEN '02:00'
                            WHEN value = 'fct'     THEN '03:00'
                            WHEN value = 'kgsl'    THEN '04:00'
                            WHEN value = 'spags'   THEN '22:00'
                            WHEN value = 'tsc'     THEN '06:00'
                            WHEN value = 'mggs'    THEN '02:00'
                            WHEN value = 'wcs'     THEN '05:00'
                            WHEN value = 'wes'     THEN '04:00'
                            WHEN value = 'dcl'     THEN '01:00'
                            WHEN value = 'tlcmild' THEN '01:00'
                            WHEN value = 'isascis' THEN '02:00'
                            WHEN value = 'htlc'    THEN '01:00'
                            WHEN value = 'shcl'    THEN '02:00'
                            WHEN value = 'ccgsl'   THEN '14:00'
                            WHEN value = 'saags'   THEN '04:00'
                            WHEN value = 'lhags'   THEN '05:00'
                            WHEN value = 'smags'   THEN '04:00'
                            WHEN value = 'luc'     THEN '01:00'
                            ELSE '22:00'
                        END
                        FROM `config` WHERE name = 'site_school_code') t3
                    )
                ELSE '22:00'
            END
        FROM `config` WHERE name = 'site_type') t1
    ),
    'User Provisioning System daily run time',
    'Time of day to schedule User Provisioning System. Please allow 30min for changes to this field to take effect.',
    (SELECT id FROM lu_config_field WHERE field = 'timepicker'),
    (SELECT id FROM lu_config_type WHERE `type` = 'External DB'),
    'true',
    'false',
    'settings',
    75
);
