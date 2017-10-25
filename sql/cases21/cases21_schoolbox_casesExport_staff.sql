SELECT
'Username' AS 'Username',
'External ID' AS 'External ID',
'Title' AS 'Title',
'First Name' AS 'First Name',
'Surname' AS 'Surname',
'Role' AS 'Role',
'Campus' AS 'Campus',
'Alt Email' AS 'Alt Email',
'Year' AS 'Year',
'House' AS 'House',
'Residential House' AS 'Residential House',
'E-Portfolio?' AS 'E-Portfolio?',
'Hide Contact Details?' AS 'Hide Contact Details?',
'Hide Timetable?' AS 'Hide Timetable?',
'Email Address From Username?' AS 'Email Address From Username?',
'Use External Mail Client?' AS 'Use External Mail Client?',
'Enable Webmail Tab?' AS 'Enable Webmail Tab?',
'Display Graph on Student Grade Card?' AS 'Display Graph on Student Grade Card?',
'Account Enabled?' AS 'Account Enabled?',
'Date of Birth' AS 'Date of Birth'

UNION

SELECT
SF.SFKEY AS 'Username',
SF.SFKEY AS 'External ID',
'' AS 'Title',
CASE
    WHEN (SF.PREF_NAME IS NULL) OR
      SF.PREF_NAME = ''
    THEN SF.FIRST_NAME
    ELSE SF.PREF_NAME
END AS 'First Name',
SF.SURNAME AS 'Surname',
'Teachers' AS Role,
'Main' AS Campus,
SF.E_MAIL AS 'Alt Email',
'' AS 'Year',
'' AS 'House',
'' AS 'Residential House',
'1' AS 'E-Portfolio?',
'0' AS 'Hide Contact Details?',
'0' AS 'Hide Timetable?',
'0' AS 'Email Address From Username?',
'0' AS 'Use External Mail Client?',
'0' AS 'Enable Webmail Tab?',
'1' AS 'Display Graph on Student Grade Card?',
'1' AS 'Account Enabled?',
'' AS 'Date of Birth'
FROM SF
WHERE SF.STAFF_STATUS = 'ACTV'
