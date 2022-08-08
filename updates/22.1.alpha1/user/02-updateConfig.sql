UPDATE config
SET description = 'Insert a default format for how user names will be displayed. Can contain: {title}, {firstname}, {preferredname}, {givenname} (preferred name if set, otherwise first name), {lastname}.'
WHERE name = 'name_format';
