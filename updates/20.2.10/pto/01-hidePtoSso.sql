UPDATE config
SET hidden   = 'true',
    editable = 'false'
WHERE name IN (
    'pto_school',
    'pto_key'
);
