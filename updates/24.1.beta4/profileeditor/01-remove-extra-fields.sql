UPDATE profile_field SET enabled = 0 WHERE
    (roletype = 'student' AND field = 'pronoun' ) OR
    (roletype = 'student' AND field = 'StudentGovernmentStudentNumber' ) OR
    (roletype = 'staff' AND field = 'externalId' ) OR
    (roletype = 'staff' AND field = 'SchoolStaffCode' ) OR
    (roletype = 'staff' AND field = 'pronoun' ) OR
    (roletype = 'staff' AND field = 'role' ) OR
    (roletype = 'staff' AND field = 'StaffDefaultMobilePhone' ) OR
    (roletype = 'staff' AND field = 'schoolHouse' ) OR
    (roletype = 'staff' AND field = 'residentialHouse' ) OR
    (roletype = 'parent' AND field = 'externalId' ) OR
    (roletype = 'parent' AND field = 'pronoun' ) OR
    (roletype = 'parent' AND field = 'campusCollection' );


DELETE profile_field_roletype_visibility
FROM profile_field_roletype_visibility
JOIN profile_field ON profile_field_roletype_visibility.profile_field_id = profile_field.id
WHERE     (profile_field.roletype = 'student' AND field = 'pronoun' ) OR
    (profile_field.roletype = 'student' AND field = 'StudentGovernmentStudentNumber' ) OR
    (profile_field.roletype = 'staff' AND field = 'externalId' ) OR
    (profile_field.roletype = 'staff' AND field = 'SchoolStaffCode' ) OR
    (profile_field.roletype = 'staff' AND field = 'pronoun' ) OR
    (profile_field.roletype = 'staff' AND field = 'role' ) OR
    (profile_field.roletype = 'staff' AND field = 'StaffDefaultMobilePhone' ) OR
    (profile_field.roletype = 'staff' AND field = 'schoolHouse' ) OR
    (profile_field.roletype = 'staff' AND field = 'residentialHouse' ) OR
    (profile_field.roletype = 'parent' AND field = 'externalId' ) OR
    (profile_field.roletype = 'parent' AND field = 'pronoun' ) OR
    (profile_field.roletype = 'parent' AND field = 'campusCollection' );

DELETE profile_field_campus_availability
FROM profile_field_campus_availability
JOIN profile_field ON profile_field_campus_availability.profile_field_id = profile_field.id
WHERE     (roletype = 'student' AND field = 'pronoun' ) OR
    (roletype = 'student' AND field = 'StudentGovernmentStudentNumber' ) OR
    (roletype = 'staff' AND field = 'externalId' ) OR
    (roletype = 'staff' AND field = 'SchoolStaffCode' ) OR
    (roletype = 'staff' AND field = 'pronoun' ) OR
    (roletype = 'staff' AND field = 'role' ) OR
    (roletype = 'staff' AND field = 'StaffDefaultMobilePhone' ) OR
    (roletype = 'staff' AND field = 'schoolHouse' ) OR
    (roletype = 'staff' AND field = 'residentialHouse' ) OR
    (roletype = 'parent' AND field = 'externalId' ) OR
    (roletype = 'parent' AND field = 'pronoun' ) OR
    (roletype = 'parent' AND field = 'campusCollection' );
