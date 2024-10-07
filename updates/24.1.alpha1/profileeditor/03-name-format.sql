-- Push down all the other configs in this area
-- Create a hole in config

UPDATE config c
    INNER JOIN lu_config_type lct on c.type_id = lct.id
SET c.sequence = c.sequence + 1
WHERE lct.type = 'Localisation'
  AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='name_format' LIMIT 1);

-- Add a new config field to specially craft students' names
-- on their profile screens. This is configured only in the
-- Profile Editor for the time being; that's why it's hidden

INSERT INTO `config`
(`name`, `value`, `title`, `description`, `options`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES
    ('name_format_student',
     '{title} {firstname} {lastname}',
     'Name Output Format (Student Profile)',
     'Insert a default format for how students\' names will be displayed on their profile screens. Can contain: {title}, {firstname}, {preferredname} (falls back to first name if empty), {lastname}, {pronoun}.',
     '',
     (SELECT id FROM lu_config_field WHERE field = 'input'),
     (SELECT id FROM lu_config_type WHERE type = 'Localisation'),
     'true',
     'true',
     'false',
     'settings',
     (SELECT c.sequence+1 FROM config c WHERE c.name='name_format' LIMIT 1)
    );