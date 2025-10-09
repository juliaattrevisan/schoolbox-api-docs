ALTER TABLE config
MODIFY module enum ('settings', 'comms', 'custom', 'oauth', 'schoolData', 'lti', 'report-export', 'feature-tours', 'openai-text', 'openai-image', 'differentiation-profiles')
DEFAULT 'settings';

INSERT INTO config (`name`, `title`, `value`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
           'differentiation_profiles_enabled',
           'Enable Differentiation Profiles',
           false,
           'Enable or disable Differentiation Profiles.',
           (SELECT id FROM lu_config_field WHERE field = 'boolean'),
           (SELECT id FROM lu_config_type WHERE type = 'Differentiation Profiles'),
           'true',
           'false',
           'false',
           'differentiation-profiles',
           (SELECT MAX(sequence)+1 FROM `config` c)
       );
