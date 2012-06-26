/* Allow multiple instances of News on homepages.
 * Alternate Source feature allows News modules from different homepages to accumulate
 */
UPDATE `homepage_comp` SET `config`='a:2:{s:13:\"componentname\";s:4:\"News\";s:4:\"size\";s:1:\"0\";}' WHERE `id`='8';
UPDATE `homepage_comp` SET `allowMultiple`='true' WHERE `id`='8';