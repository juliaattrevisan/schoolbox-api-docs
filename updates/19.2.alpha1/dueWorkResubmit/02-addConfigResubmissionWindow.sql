-- Add a config option which defines resubmission window
INSERT INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES
(
    'lms_resubmit_window',
    '+1 week',
    'Resubmission Window',
    'The time period allowed to resubmit from when the resubmission is requested',
    (SELECT id FROM lu_config_field WHERE `field` = 'input'),
    null,
    (SELECT id FROM lu_config_type WHERE `type` = 'LMS Policy'),
    true,
    false,
    false,
    'settings',
    null
);
