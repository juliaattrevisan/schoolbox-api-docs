/* New config to allow teachers to delete feedback from assessment history */
INSERT INTO config(
    name,
    value,
    title,
    description,
    field_id,
    options,
    type_id,
    editable,
    hidden,
    required,
    sequence
) VALUES (
             'lms_permit_staff_feedback_delete',
             '0',
             'Allow staff modification of the due work timeline',
             'If toggled to \'ON\', staff can delete feedback from the due work timeline, altering historically published grades. If toggled to \'OFF\', only superusers may delete feedback.',
             (SELECT id FROM lu_config_field WHERE field = 'boolean'),
             '',
             (SELECT id FROM lu_config_type WHERE type = 'LMS Policy'),
             'true',
             'false',
             'false',
             4
         );
