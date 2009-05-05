
insert  into `homepage_comp`(`id`,`size`,`method`,`name`,`description`,`filename`,`config`,`invisible`) values (37,1,'outputAudio','Audio','Lists audio files and allows them to be played.','audio.html','a:3:{s:4:\"sort\";s:4:\"Name\";s:7:\"sortdir\";s:3:\"Asc\";s:4:\"size\";i:0;}','false');
insert  into `homepage_comp`(`id`,`size`,`method`,`name`,`description`,`filename`,`config`,`invisible`) values (38,1,'outputPodcast','Podcast','Subscribe to a podcast ','podcast.html','a:2:{s:3:\"url\";s:0:\"\";s:4:\"size\";i:0;}','false');

UPDATE `homepage_comp` SET `config` = 'a:1:{s:6:"pollID";i:0;}' WHERE `id` = 33;
UPDATE `homepage_comp` SET `config` = 'a:5:{s:6:"filter";a:5:{i:0;s:8:"document";i:1;s:5:"audio";i:2;s:5:"video";i:3;s:5:"image";i:4;s:5:"other";}s:13:"filterOptions";a:5:{i:0;s:8:"document";i:1;s:5:"audio";i:2;s:5:"video";i:3;s:5:"image";i:4;s:5:"other";}s:4:"sort";s:4:"Date";s:7:"sortdir";s:4:"Desc";s:4:"size";i:0;}' WHERE `id` = 3;