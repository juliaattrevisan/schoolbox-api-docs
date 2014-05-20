ALTER TABLE `cmsLog`
CHANGE `object` `object`
  ENUM('folder','file','link','content','myfile','myfolder')
  CHARSET utf8
  COLLATE utf8_general_ci
  DEFAULT 'folder'
  NOT NULL;
