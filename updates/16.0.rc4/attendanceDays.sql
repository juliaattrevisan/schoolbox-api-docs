INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES ('external_attendance_days',
        '1',
        'Allowed Days for Attendance',
        'This is the number of days include today that staff are able to mark attendance in the calendar, weekends are included',
        4,
        '0:1:2:3:4:5:6:7',
        7,
        'true',
        'false',
        'true');
