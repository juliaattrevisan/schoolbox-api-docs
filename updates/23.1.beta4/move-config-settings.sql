SET @module_settings_type_id=(SELECT id FROM lu_config_type WHERE type='External DB');
SET @staff_directory_enabled_sequence=(SELECT sequence FROM config WHERE name='staff_directory_enabled');
# Make room for two fields that will be moved to new positions under staff_directory_sequence.
UPDATE config SET sequence=sequence+2 WHERE type_id=@module_settings_type_id AND sequence>@staff_directory_enabled_sequence ORDER BY sequence DESC;
# Move two fields from higher sequence numbers to underneath staff_directory_enabled;
UPDATE config SET sequence=@staff_directory_enabled_sequence+1 WHERE name='staff_dir_category_restrict';
UPDATE config SET sequence=@staff_directory_enabled_sequence+2 WHERE name='staff_dir_position_title_restrict';
