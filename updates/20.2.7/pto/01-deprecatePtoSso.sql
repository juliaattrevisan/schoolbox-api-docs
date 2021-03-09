UPDATE config
SET hidden   = 'true',
    editable = 'false'
WHERE name IN (
    'external_pto_conference_school_id',
    'external_pto_conference_school_remote_secret',
    'pto_school',
    'pto_key'
);
