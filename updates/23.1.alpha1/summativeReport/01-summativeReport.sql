#### backup the options column in case we need a backup
ALTER TABLE summative_report ADD COLUMN _options longtext CHARSET 'utf8mb4' DEFAULT NULL AFTER options;

UPDATE summative_report SET _options=options
WHERE _options IS NULL AND options<>'';

# Clear contents of `options`
UPDATE summative_report SET options='';
