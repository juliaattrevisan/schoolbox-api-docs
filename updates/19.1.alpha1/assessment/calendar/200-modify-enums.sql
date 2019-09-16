ALTER TABLE filter_page
    CHANGE COLUMN page page ENUM ('pending', 'grades', 'markbook', 'analytics', 'dueWorkCal', 'courseMarkbook','rubricMarkbook','assessmentCal');
ALTER TABLE filter_selection
    CHANGE COLUMN page page ENUM ('pending', 'grades', 'markbook', 'analytics', 'dueWorkCal', 'courseMarkbook','rubricMarkbook','assessmentCal');
