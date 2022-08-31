-- Push down all the other configs in this area
-- Create a hole in config

UPDATE config c
INNER JOIN lu_config_type lct on c.type_id = lct.id
SET c.sequence = c.sequence + 1
WHERE lct.type = 'External DB'
AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='external_liss_default_event_type' LIMIT 1);

-- Add the choice between using numeric Teacher Ids
-- and alphanumeric Teacher Codes, when importing
-- data from liss/edval

INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
    'external_liss_teacher_identifier',
    'teacherId',
    'External LISS Staff ID Field',
    'The LISS protocol provides two options for the external ID. Select the one that matches your Schoolbox External ID.',
    (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
    'teacherId;Teacher ID:teacherCode;Teacher Code',
    (SELECT id FROM lu_config_type WHERE type = 'External DB'),
    'true',
    'false',
    'true',
    'settings',
    (SELECT sequence+1 FROM `config` c WHERE c.name = 'external_liss_default_event_type')
);