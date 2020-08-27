ALTER TABLE pastoral_report
ADD COLUMN include_students_without_year BOOL NOT NULL DEFAULT FALSE AFTER years,
ADD COLUMN include_students_without_house BOOL NOT NULL DEFAULT FALSE AFTER houses;
