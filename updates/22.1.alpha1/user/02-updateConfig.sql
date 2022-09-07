UPDATE config
SET description = 'Insert a default format for how user names will be displayed. Can contain: {title}, {firstname}, {preferredname} (falls back to first name if empty), {lastname}.'
WHERE name = 'name_format';
