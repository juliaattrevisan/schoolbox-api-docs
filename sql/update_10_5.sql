
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
INSERT INTO error (id, message) VALUES (108, 'You do not have permission to leave comments on this blog.')