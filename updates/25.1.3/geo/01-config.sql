-- Push down all the other configs in this area
-- Create a hole in config

UPDATE config c
SET c.sequence = c.sequence + 2
WHERE c.type_id = (
    SELECT id
    FROM lu_config_type
    WHERE
        `type` = "3rd Party Services"
)
  AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='geosnapshot_api_key' LIMIT 1);

INSERT INTO `config`
(`name`, `value`, `title`, `description`, `options`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
           'geosnapshot_launch_url',
           '',
           'GeoSnapshot Management Launch URL',
           'The URL to launch GeoSnapshot SSO. This is typically provided by your GeoSnapshot administrator.',
           '',
           (SELECT id FROM lu_config_field WHERE field = 'input'),
           (
               SELECT id
               FROM lu_config_type
               WHERE
                   `type` = "3rd Party Services"
           ),
           'true',
           'false',
           'false',
           'settings',
           (SELECT c.sequence+1 FROM config c WHERE c.name = 'geosnapshot_api_key' AND c.type_id = (
               SELECT id
               FROM lu_config_type
               WHERE
                   `type` = "3rd Party Services"
           ) LIMIT 1)
       );
INSERT INTO `config`
(`name`, `value`, `title`, `description`, `options`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
           'geosnapshot_library_url',
           'https://geosnapshot.com/library',
           'GeoSnapshot Management Library URL',
           'The URL to launch GeoSnapshot Management Interface. This is typically provided by your GeoSnapshot administrator.',
           'https://geosnapshot.com/library',
           (SELECT id FROM lu_config_field WHERE field = 'input'),
           (
               SELECT id
               FROM lu_config_type
               WHERE
                   `type` = "3rd Party Services"
           ),
           'true',
           'false',
           'false',
           'settings',
           (SELECT c.sequence+2 FROM config c WHERE c.name = 'geosnapshot_api_key' AND c.type_id = (
               SELECT id
               FROM lu_config_type
               WHERE
                   `type` = "3rd Party Services"
           ) LIMIT 1)
       );