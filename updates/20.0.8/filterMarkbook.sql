
alter table filter_page modify page enum('pending', 'grades', 'markbook', 'classMarkbook', 'analytics', 'dueWorkCal', 'courseMarkbook', 'rubricMarkbook', 'assessmentCal') null;

update filter_page set page = 'classMarkbook' WHERE page = 'markbook';

alter table filter_page modify page enum('pending', 'grades', 'classMarkbook', 'analytics', 'dueWorkCal', 'courseMarkbook', 'rubricMarkbook', 'assessmentCal') null;
