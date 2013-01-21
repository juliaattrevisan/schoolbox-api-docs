/* Unique error message when someone is using the search filter on an unspecified year level
 * in Parent Directory (/modules/parent/10)
 *
 * This is more meaningful than the previously-generic Error 19
 */
INSERT INTO `error`(`id`,`message`) VALUES ('155','You cannot view the Parent Directory for the specified year level.');