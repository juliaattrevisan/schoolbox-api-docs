INSERT INTO filter (entity, class)
VALUES ('Schoolbox\\Learning\\Badge\\Award\\Mapper',
        'Schoolbox\\Filter\\Filters\\Award\\Term\\Definition'),
       ('Schoolbox\\Learning\\Badge\\Award\\Mapper',
        'Schoolbox\\Filter\\Filters\\Award\\Status\\Definition'),
       ('Schoolbox\\Learning\\Badge\\Award\\Mapper',
        'Schoolbox\\Filter\\Filters\\Badge\\Category\\Definition');

ALTER TABLE filter_page
    MODIFY page ENUM('pending', 'grades', 'classMarkbook', 'analytics', 'dueWorkCal', 'courseMarkbook', 'rubricMarkbook', 'assessmentCal', 'badgeList', 'awardList') NULL;

ALTER TABLE filter_selection
    MODIFY page ENUM('pending', 'grades', 'classMarkbook', 'analytics', 'dueWorkCal', 'courseMarkbook', 'rubricMarkbook', 'assessmentCal', 'badgeList', 'awardList') NULL;
