ALTER TABLE filter_page
    MODIFY page ENUM('pending', 'grades', 'classMarkbook', 'analytics', 'dueWorkCal', 'courseMarkbook', 'rubricMarkbook', 'assessmentCal', 'badgeList', 'awardList') NULL;

ALTER TABLE filter_selection
    MODIFY page ENUM('pending', 'grades', 'classMarkbook', 'analytics', 'dueWorkCal', 'courseMarkbook', 'rubricMarkbook', 'assessmentCal', 'badgeList', 'awardList') NULL;
