-- Make a few tweaks to the DP toggle

UPDATE config
SET
    description = "If toggled to 'ON', Student Differentiation Profiles can be created for students and used to customise learning activities."
WHERE
    name = 'differentiation_profiles_enabled';

UPDATE config
SET
    title = "Enable Differentiation"
WHERE
    name = 'differentiation_profiles_enabled';
