UPDATE differentiation_profiles_config
SET
    terminology = "Goals"
WHERE
    name = 'goals';

UPDATE differentiation_profiles_config
SET
    description = "List of Needs / Challenges that the student faces"
WHERE
    name = 'needs_challenges';
