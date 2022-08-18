INSERT INTO filter (entity, class)
VALUES ('Schoolbox\\Pastoral\\Mapper',
        'Schoolbox\\Filter\\Filters\\Pastoral\\Creator\\Definition');

ALTER TABLE filter_page
    MODIFY page ENUM('pending', 'grades', 'classMarkbook', 'analytics', 'dueWorkCal', 'courseMarkbook', 'rubricMarkbook', 'assessmentCal', 'badgeList', 'awardList', 'classPastoral') NULL;

ALTER TABLE filter_selection
    MODIFY page ENUM('pending', 'grades', 'classMarkbook', 'analytics', 'dueWorkCal', 'courseMarkbook', 'rubricMarkbook', 'assessmentCal', 'badgeList', 'awardList', 'classPastoral') NULL;
