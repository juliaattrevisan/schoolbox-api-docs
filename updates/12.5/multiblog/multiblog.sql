/* Allow multiple instances of Blog on homepages.
 * Alternate Source feature allows Blog modules from different homepages to accumulate
 */
UPDATE `homepage_comp` SET `config`='a:4:{s:13:\"componentname\";s:4:\"Blog\";s:4:\"size\";s:1:\"3\";s:13:\"comments_size\";s:1:\"2\";s:13:\"homepage-blog\";s:0:\"\";}' WHERE `id`='39';
UPDATE `homepage_comp` SET `allowMultiple`='true' WHERE `id`='39';