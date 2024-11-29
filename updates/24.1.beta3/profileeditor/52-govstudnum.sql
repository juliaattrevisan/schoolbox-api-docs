-- Govt Student Numebr is now in terminology
-- but it's not linked up with the corresponding Profile Field.
-- This was a result of Penola's custom work where we introduced
-- it to the Summative Reports.

UPDATE profile_field
SET term = 'govtStudentNumber'
WHERE field = 'StudentGovernmentStudentNumber'
AND roletype = 'student'
LIMIT 1;
