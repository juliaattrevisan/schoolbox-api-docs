-- Add Work Type filter for customisation records
INSERT INTO filter (entity, class)
VALUES
    (
        'Schoolbox\\CustomisationRecord\\Mapper',
        'Schoolbox\\Filter\\Filters\\CustomisationRecord\\WorkType\\Definition'
    );
