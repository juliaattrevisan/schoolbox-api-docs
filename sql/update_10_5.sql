
/* Language translation
 * Image scroller
 */
INSERT INTO homepage_comp (size, method, name, description, filename) VALUES (1, 'outputTranslate', 'Language Translator', 'Translates text and webpages into another language', 'translate.html');
INSERT INTO homepage_comp (size, method, NAME, description, filename, config) VALUES (2, 'outputScroller', 'Image Scroller', 'An image carousel.', 'scroller.html', 'a:1:{s:5:"pixel";i:400;}');

/* ePortfolio
 * NB: Include schema of all tables with eportfolio_ prefix.
 */
INSERT INTO error (id, message) VALUES (106, 'You do not have permission to modify or delete this link.');
INSERT INTO error (id, message) VALUES (107, 'You do not have permission to modify this blog.');
INSERT INTO error (id, message) VALUES (108, 'You do not have permission to leave comments on this blog.');
INSERT INTO error (id, message) VALUES (109, 'You do not have permission to publish this file.');
INSERT INTO error (id, message) VALUES (110, 'You do not have permission to modify these notes.');
INSERT INTO error (id, message) VALUES ('111', 'There are no blogs in this archive.');

/* Published Files
 */
ALTER TABLE myFile ADD COLUMN published DATETIME;

/* Private Notes
 */
CREATE TABLE eportfolio_privateNotes (
    uid INT(8),
    privateNotes TEXT
)

/* Assess
 * NB: Include schema of all tables with assess prefix.
 */
INSERT INTO homepage_comp (size, method, `name`, description, filename, config, invisible) VALUES (1, 'outputAssess', 'Online Assessment', 'Online tests and quizzes.', 'assess.html', '', 'false')

INSERT INTO file_type (id, name) VALUES (11, 'Online Assessment');

INSERT INTO error (id, message) VALUES (112, 'This assessment no longer exists.');
INSERT INTO error (id, message) VALUES (113, 'You have not answered all questions; please go back and submit them.');
INSERT INTO error (id, message) VALUES (114, 'You have not selected an attempt to review.');
INSERT INTO error (id, message) VALUES (115, 'This section no longer exists.');