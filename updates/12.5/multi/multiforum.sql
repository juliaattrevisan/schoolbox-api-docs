/* Allow multiple instances of Forum on homepages.
 * Alternate Source feature allows Forum modules from different homepages to accumulate
 */
UPDATE `homepage_comp` SET `allowMultiple`='true' WHERE `id`='5';
