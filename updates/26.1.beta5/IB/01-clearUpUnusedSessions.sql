-- Removing all the sample sessions that were added in the previous updates.
DELETE
sl
FROM ib_registration_session_student_subject_language sl
JOIN ib_registration_session_student rss
    ON rss.id = sl.registration_session_student_id
JOIN ib_registration_sessions rs
    ON rs.id = rss.registration_session_id
WHERE (rs.system_purpose_id, rs.academic_year, rs.exam_session) IN (
    (1, '2025', 'November'),
    (1, '2026', 'May'),
    (2, '2025', 'November'),
    (3, '2025', 'May')
);

DELETE
rss
FROM ib_registration_session_student rss
JOIN ib_registration_sessions rs
    ON rs.id = rss.registration_session_id
WHERE (rs.system_purpose_id, rs.academic_year, rs.exam_session) IN (
    (1, '2025', 'November'),
    (1, '2026', 'May'),
    (2, '2025', 'November'),
    (3, '2025', 'May')
);

DELETE
FROM ib_registration_sessions
WHERE (system_purpose_id, academic_year, exam_session) IN (
   (1, '2025', 'November'),
   (1, '2026', 'May'),
   (2, '2025', 'November'),
   (3, '2025', 'May')
);

-- Removing the Diploma Programme Year 1 system purpose.
DELETE
FROM ib_system_purposes
WHERE name = 'Diploma Programme Year 1';


