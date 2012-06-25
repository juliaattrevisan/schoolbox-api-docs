/* Allow multiple instances of Wiki on homepages.
 * Alternate Source feature allows Wiki modules from different homepages to accumulate
 */
UPDATE `homepage_comp` SET `config`='a:5:{s:13:"componentname";s:4:"Wiki";s:13:"homepage-wiki";s:12:"/homepage/73";s:4:"sort";s:7:"Heading";s:7:"sortdir";s:4:"Desc";s:4:"size";s:1:"0";}' WHERE `id`='39';
UPDATE `homepage_comp` SET `allowMultiple`='true' WHERE `id`='34';