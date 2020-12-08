INSERT INTO filter (entity, class)
VALUES (
   'Schoolbox\\Learning\\Badge\\Mapper',
   'Schoolbox\\Filter\\Filters\\Badge\\Category\\Definition'
);

ALTER TABLE filter_page
    MODIFY page ENUM('pending', 'grades', 'classMarkbook', 'analytics', 'dueWorkCal', 'courseMarkbook', 'rubricMarkbook', 'assessmentCal', 'badgeList') NULL;

ALTER TABLE filter_selection
    MODIFY page ENUM('pending', 'grades', 'classMarkbook', 'analytics', 'dueWorkCal', 'courseMarkbook', 'rubricMarkbook', 'assessmentCal', 'badgeList') NULL;
