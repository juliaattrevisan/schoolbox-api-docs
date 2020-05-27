
alter table filter_page modify page enum('pending', 'grades', 'classMarkbook', 'analytics', 'dueWorkCal', 'courseMarkbook', 'rubricMarkbook', 'assessmentCal') null;
alter table filter_selection modify page enum('pending', 'grades', 'classMarkbook', 'analytics', 'dueWorkCal', 'courseMarkbook', 'rubricMarkbook', 'assessmentCal') null;
