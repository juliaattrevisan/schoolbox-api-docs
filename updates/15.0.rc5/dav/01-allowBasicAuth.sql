INSERT INTO `config`(
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `options`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`
)
VALUES (
    'webdav_auth_type',
    'digest',
    'WebDAV authentication type',
    'Select a WebDAV authentication type. You should use "Digest" unless your school uses SAML, in which case you must use "Basic".',
    4, -- option list
    'digest:basic',
    4, -- resources
    'true',
    'false',
    'false'
);
