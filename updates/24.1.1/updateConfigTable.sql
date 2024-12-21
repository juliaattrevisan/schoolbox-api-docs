# Add new module 'feature-tours' so that admin search result for 'Enable Feature Tours' can point to adminv2/feature-tours instead of adminv2/settings/site.
ALTER TABLE config CHANGE COLUMN module module enum('settings','comms','custom','oauth','schoolData','lti','report-export','feature-tours') DEFAULT 'settings';
UPDATE config SET module='feature-tours' WHERE name='tours_enabled';

# Hide two options that do not exist on any Admin pages.
UPDATE config SET hidden='true' WHERE name IN ('purifier_cache_dir', 'twig_cache_dir');

UPDATE config SET hidden='true' WHERE type_id = (SELECT id FROM lu_config_type WHERE type = 'Paths');
