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
'Superuser?' AS 'Superuser?',
'Account Enabled?' AS 'Account Enabled?',
'Date of Birth' AS 'Date of Birth'

UNION

SELECT
ST.STKEY AS 'Username',
ST.STKEY AS 'External ID',
'' AS 'Title',
ST.PREF_NAME AS 'First Name',
ST.SURNAME AS 'Surname',
'Year ' + ST.SCHOOL_YEAR + ' Students' AS Role,
'Main,Year ' + ST.SCHOOL_YEAR AS Campus,
'' AS 'Alt Email',
ST.SCHOOL_YEAR AS 'Year',
'' AS 'House',
'' AS 'Residential House',
'1' AS 'E-Portfolio?',
'0' AS 'Hide Contact Details?',
'0' AS 'Hide Timetable?',
'1' AS 'Email Address From Username?',
'0' AS 'Use External Mail Client?',
'1' AS 'Enable Webmail Tab?',
'1' AS 'Display Graph on Student Grade Card?',
'0' AS 'Superuser?',
'1' AS 'Account Enabled?',
CONVERT(varchar(10), BIRTHDATE, 126) AS 'Date of Birth'
FROM ST
WHERE ST.STATUS IN ('ACTV','LVNG','FUT')