/* Allow multiple instances of Image Gallery on homepages.
 * Alternate Source feature allows Image Gallery modules from different homepages to accumulate
 */
UPDATE `homepage_comp` SET `config`='a:3:{s:13:"componentname";s:13:"Image Gallery";s:4:"size";s:1:"0";s:21:"homepage-imagegallery";s:0:"";}' WHERE `id`='23';
UPDATE `homepage_comp` SET `allowMultiple`='true' WHERE `id`='23';