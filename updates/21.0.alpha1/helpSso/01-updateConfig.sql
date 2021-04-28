-- Enable config for all but Help
UPDATE config SET value =
(SELECT COUNT(*) FROM config c2 WHERE name = 'site_school_code' AND value != 'help')
WHERE name = 'show_schoolbox_help_link';
