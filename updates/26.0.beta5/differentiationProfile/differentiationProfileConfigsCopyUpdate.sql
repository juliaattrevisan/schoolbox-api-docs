-- Update default deployed dataset copy fixes (typos + capitalization)

UPDATE differentiation_profiles_config
SET
    title = 'Adjustment Category',
    terminology = 'Adjustment Category',
    description = 'Category of adjustment'
WHERE
    name = 'adjustment_category';

UPDATE differentiation_profiles_config
SET
    title = 'Adjustment Level',
    terminology = 'Adjustment Level',
    description = 'Level of adjustment required'
WHERE
    name = 'adjustment_level';

UPDATE differentiation_profiles_config
SET
    title = 'Overview',
    terminology = 'Overview',
    description = 'Concise overview of learner attributes essential to effective differentiation'
WHERE
    name = 'overview';

UPDATE differentiation_profiles_config
SET
    title = 'Interests / Strengths',
    terminology = 'Interests / Strengths',
    description = 'List of students'' interests and strengths'
WHERE
    name = 'interests_strengths';

UPDATE differentiation_profiles_config
SET
    title = 'Learning Needs',
    terminology = 'Learning Needs',
    description = 'List of needs / challenges that the student faces'
WHERE
    name = 'needs_challenges';

UPDATE differentiation_profiles_config
SET
    title = 'Strategies / Adjustments',
    terminology = 'Specific Strategies',
    description = 'Specific strategies to consider when differentiating for the student'
WHERE
    name = 'strategies_adjustments';

UPDATE differentiation_profiles_config
SET
    title = 'Goals',
    terminology = 'Goals',
    description = 'Students'' individual learning goals'
WHERE
    name = 'goals';
