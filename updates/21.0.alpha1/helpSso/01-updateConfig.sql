-- Enable config for all but Help
UPDATE config c1
INNER JOIN config c2 ON c2.name = 'site_school_code'
SET c1.value = c2.value != 'help',
    c1.hidden = 'false'
WHERE c1.name = 'show_schoolbox_help_link';
