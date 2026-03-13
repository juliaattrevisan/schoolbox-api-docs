-- Add Work Type filter for customisation records
INSERT INTO filter (entity, class)
VALUES
    (
        'Schoolbox\\CustomisationRecord\\Mapper',
        'Schoolbox\\Filter\\Filters\\CustomisationRecord\\WorkType\\Definition'
    );

-- Get the filter ID that was just inserted
SET @filter_id = LAST_INSERT_ID();

-- Add filter page record for customisationRecord page
INSERT INTO filter_page (filter_id, page, enabled, user_configurable)
VALUES
    (@filter_id, 'customisationRecord', 1, 1);
