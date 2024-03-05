-- Add new config into 3rd Party Services for Firebase
SET @configType = (SELECT id FROM lu_config_type WHERE type = '3rd Party Services');
SET @configField = (SELECT id FROM lu_config_field WHERE field = 'textarea');
INSERT INTO config (
    `name`,
    `title`,
    `value`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
) VALUES (
    'firebase_public_config',
    'Firebase Public Configuration',
    '',
    'Publicly available configuration for Firebase app for this Schoolbox instance. This is a JSON object which may be copied from Firebase, via Project Settings > General > Your apps > (your app nickname) > SDK setup and configuration > Config. Paste in only the {, } and their contents.',
    @configField,
    @configType,
    'true',
    'false',
    'false',
    'settings',
    (SELECT MAX(sequence) + 1 FROM `config` c WHERE c.type_id = @configType)
);

INSERT INTO config (
    `name`,
    `title`,
    `value`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
) VALUES (
    'firebase_private_key',
    'Firebase Private Key',
    '',
    'Private configuration for Firebase app for this Schoolbox instance. This is a JSON object which may be downloaded from Firebase, via Project Settings > Service Accounts > Firebase Admin SDK > Generate new private key. Paste its contents into this field.',
    @configField,
    @configType,
    'true',
    'false',
    'false',
    'settings',
    (SELECT MAX(sequence) + 1 FROM `config` c WHERE c.type_id = @configType)
);

INSERT INTO config (
    `name`,
    `title`,
    `value`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
) VALUES (
    'firebase_web_push_public_key',
    'Firebase Web Push Public Key',
    '',
    'Public key for Firebase app for this Schoolbox instance, used for registering a device with Firebase. This is a key which may be generated within Firebase, via Project Settings > Cloud Messaging > Web configuration > Web push certificates > Generate key pair. Paste its value into this field.',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    @configType,
    'true',
    'false',
    'false',
    'settings',
    (SELECT MAX(sequence) + 1 FROM `config` c WHERE c.type_id = @configType)
);
