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
    'differentiationProfile',
    'customisationRecord'
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
    'differentiationProfile',
    'customisationRecord'
    ) NULL;

-- Inserting available customisation record filters
INSERT INTO filter (entity, class)
VALUES
    (
        'Schoolbox\\CustomisationRecord\\Mapper',
        'Schoolbox\\Filter\\Filters\\CustomisationRecord\\Name\\Definition'
    ),
    (
        'Schoolbox\\CustomisationRecord\\Mapper',
        'Schoolbox\\Filter\\Filters\\CustomisationRecord\\YearLevel\\Definition'
    ),
    (
        'Schoolbox\\CustomisationRecord\\Mapper',
        'Schoolbox\\Filter\\Filters\\CustomisationRecord\\Campus\\Definition'
    ),
    (
        'Schoolbox\\CustomisationRecord\\Mapper',
        'Schoolbox\\Filter\\Filters\\CustomisationRecord\\AdjustmentCategory\\Definition'
    ),
    (
        'Schoolbox\\CustomisationRecord\\Mapper',
        'Schoolbox\\Filter\\Filters\\CustomisationRecord\\AdjustmentLevel\\Definition'
    ),
    (
        'Schoolbox\\CustomisationRecord\\Mapper',
        'Schoolbox\\Filter\\Filters\\CustomisationRecord\\Term\\Definition'
    );
