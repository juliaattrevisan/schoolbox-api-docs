UPDATE config
SET description = 'The entered time will be automatically populated into all assessment due times, if left blank 11:59pm will be used instead'
WHERE (configID = '521');
