

UPDATE `allowedFileTypes` SET `inline` = 'false'  WHERE mime IN('application/msaccess'
'application/msword',
'application/onenote',
'application/vnd.ms-excel',
'application/vnd.ms-powerpoint',
'application/vnd.ms-project',
'application/vnd.ms-publisher',
'application/vnd.ms-xpsdocument',
'application/vnd.openxmlformats-officedocument.presentationml.presentation',
'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
'application/vnd.openxmlformats-officedocument.presentationml.template',
'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
'video/avi')
