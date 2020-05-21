-- Make MS Teams integration a dropdown option
-- The config option's value doesn't have to change, because the existing values
-- map directly onto what the new options should be.
UPDATE config SET
    field_id    = (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
    options     = '0;None:1;Staff Only:2;Staff and Students',
    description = 'Allow users in the selected role types to create Microsoft Teams links.'
WHERE name = 'teams_enabled';
