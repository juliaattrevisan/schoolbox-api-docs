# Remove any remnants of the folder filter from the class markbook:
# * user's selected folders (filter_selection)
# * the default selections for a filter for a role type (filter_default)
# * the availability of a filter for a role type (filter_option)
# * the base configuration for the filter (filter_page)

DELETE fs FROM filter_selection fs
INNER JOIN filter f ON fs.filter_id = f.id
WHERE fs.page = 'classMarkbook' AND f.class = 'Schoolbox\\Filter\\Filters\\Assessment\\Folder\\TeacherInvolved\\Definition';

DELETE fd FROM filter_default fd
INNER JOIN filter_option fo ON fd.filter_option_id = fo.id
INNER JOIN filter_page   fp ON fo.filter_page_id   = fp.id
INNER JOIN filter        f  ON fp.filter_id        = f.id
WHERE fp.page = 'classMarkbook' AND f.class = 'Schoolbox\\Filter\\Filters\\Assessment\\Folder\\TeacherInvolved\\Definition';

DELETE fo FROM filter_option fo
INNER JOIN filter_page fp ON fo.filter_page_id = fp.id
INNER JOIN filter      f  ON fp.filter_id      = f.id
WHERE fp.page = 'classMarkbook' AND f.class = 'Schoolbox\\Filter\\Filters\\Assessment\\Folder\\TeacherInvolved\\Definition';

DELETE fp FROM filter_page fp
INNER JOIN filter f ON fp.filter_id = f.id
WHERE fp.page = 'classMarkbook' AND f.class = 'Schoolbox\\Filter\\Filters\\Assessment\\Folder\\TeacherInvolved\\Definition';
