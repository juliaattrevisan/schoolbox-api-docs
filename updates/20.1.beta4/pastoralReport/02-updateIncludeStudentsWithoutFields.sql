/**
 * A report without any selected year levels or houses should be expected to
 * return records for students which don't have year levels or houses.
 */
UPDATE pastoral_report SET include_students_without_year = TRUE WHERE years = '';
UPDATE pastoral_report SET include_students_without_house = TRUE WHERE houses = '';
