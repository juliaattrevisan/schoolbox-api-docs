-- We now solely use 'site_school_code' to identify the organisation that owns this instance
DELETE FROM config WHERE name = 'site_location';
