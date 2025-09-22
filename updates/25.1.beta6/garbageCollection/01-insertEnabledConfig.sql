# Put this setting after site_static_asset_serve_from_cdn, making room if required
SELECT @sequence := sequence FROM config WHERE name = 'site_static_asset_serve_from_cdn';
SELECT @field    := id FROM `lu_config_field` WHERE `field` = 'boolean';
SELECT @type     := id FROM lu_config_type WHERE type = 'Resources';
UPDATE config SET sequence = sequence + 1 WHERE type_id = @type AND sequence > @sequence;

INSERT INTO `config` (
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
) VALUES (
    'storage_cleanup_enabled',
    '0',
    'Enable Storage Cleanup',
    'If toggled to ‘ON’, the storage cleanup tool will run nightly, detecting and deleting unused uploaded files.  Otherwise, it will only be run by Schoolbox support on request.',
    @field,
    @type,
    'false',
    'true',
    'true',
    'settings',
    @sequence
);
