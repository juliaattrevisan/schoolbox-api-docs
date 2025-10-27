-- Updating ENUM for filters
ALTER TABLE filter_page
    MODIFY page ENUM (
    'pending',
    'grades',
    'classMarkbook',
    'analytics',
    'dueWorkCal',
    'courseMarkbook',
    'rubricMarkbook',
    'assessmentCal',
    'badgeList',
    'awardList',
    'classPastoral',
    'differentiationProfile'
    ) NULL;

ALTER TABLE filter_selection
    MODIFY page ENUM (
    'pending',
    'grades',
    'classMarkbook',
    'analytics',
    'dueWorkCal',
    'courseMarkbook',
    'rubricMarkbook',
    'assessmentCal',
    'badgeList',
    'awardList',
    'classPastoral',
    'differentiationProfile'
    ) NULL;

-- Inserting available differentiation profile filters
INSERT INTO filter (entity, class)
VALUES
    (
        'Schoolbox\\DifferentiationProfile\\Mapper',
        'Schoolbox\\Filter\\Filters\\DifferentiationProfile\\Name\\Definition'
    ),
    (
        'Schoolbox\\DifferentiationProfile\\Mapper',
        'Schoolbox\\Filter\\Filters\\DifferentiationProfile\\YearLevel\\Definition'
    ),
    (
        'Schoolbox\\DifferentiationProfile\\Mapper',
        'Schoolbox\\Filter\\Filters\\DifferentiationProfile\\Campus\\Definition'
    ),
    (
        'Schoolbox\\DifferentiationProfile\\Mapper',
        'Schoolbox\\Filter\\Filters\\DifferentiationProfile\\AdjustmentCategory\\Definition'
    ),
    (
        'Schoolbox\\DifferentiationProfile\\Mapper',
        'Schoolbox\\Filter\\Filters\\DifferentiationProfile\\AdjustmentLevel\\Definition'
    );
