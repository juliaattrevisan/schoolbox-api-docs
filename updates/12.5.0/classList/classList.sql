/* Add students homepage component
 */
INSERT INTO `homepage_comp`(`id`,`size`,`method`,`name`,`description`,`homepageCategoryID`,`filename`,`config`,`invisible`,`allowMultiple`) VALUES ( NULL,'0','outputClassList','Class Students','Output class list','3','students.html','','false','false');

/* Teachers homepage component now has optional avatars
 */
UPDATE `homepage_comp` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `id`='30';
UPDATE `homepage_comp_location` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `component`='30';

/* Students homepage component now has optional avatars
 */
UPDATE `homepage_comp` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `id`='51';
UPDATE `homepage_comp_location` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `component`='51';

/* Managers list now has optional avatars
 */
UPDATE `homepage_comp` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `id`='4';
UPDATE `homepage_comp_location` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `component`='4';

/* Members list  now has optional avatars
 */
UPDATE `homepage_comp` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `id`='13';
UPDATE `homepage_comp_location` SET `config`='a:1:{s:16:"displayPortraits";s:4:"grid";}' WHERE `component`='13';
