#Create flag to enable human readable sync
UPDATE config SET sequence = sequence+1 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'External DB') and sequence > 28;


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
             'synergetic_mark_sync_value',
             '0',
             'Synergetic Mark Sync Value',
             'Determines the format of the mark written to Synergetic. Normalised is a percentage, null values are -1. Human Readable is the string you see in Schoolbox',
             (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
             '0;Normalised:1;Human Readable',
             (SELECT id FROM lu_config_type WHERE type = 'External DB'),
             'true',
             'false',
             'true',
             29
         );
