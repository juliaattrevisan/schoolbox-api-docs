UPDATE config
SET
    description = "If toggled to 'ON', Differentiation Profiles can be created for students."
WHERE
    name = 'differentiation_profiles_enabled';
