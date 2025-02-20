# capitalize the term 'lessonPlan' in the localisation table
UPDATE localisation
SET singular_form = 'Lesson Plan'
WHERE term = 'lessonPlan';
