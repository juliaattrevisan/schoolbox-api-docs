# badge.body.
#  * edit page: https://vagrant.dev.schoolbox.cloud/learning/badge/create
#  * Client-side limit found and updated? YES
ALTER TABLE badge CHANGE COLUMN body body mediumtext COLLATE utf8mb4_unicode_ci NOT NULL;

# eportfolio_component_blog.body
#  * edit page: https://vagrant.dev.schoolbox.cloud/eportfolio/blog/17/16
#  * Client-side limit found and updated? YES (also for homepage blog - blog.body was already mediumtext)
ALTER TABLE eportfolio_component_blog CHANGE COLUMN body body mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL;

# etherpad_pad.body
#  * edit page: https://vagrant.dev.schoolbox.cloud/collabdoc/edit/1/83
#  * Client-side length limit found and updated? N/A (Etherpad is a third-party component that handles its own character limit.)
ALTER TABLE etherpad_pad CHANGE COLUMN body body mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL;

# event.detail
#  * edit page: https://vagrant.dev.schoolbox.cloud/calendar/event/1891/modify
#  * Client-side length limit found and updated? YES
ALTER TABLE event CHANGE COLUMN detail detail mediumtext COLLATE utf8mb4_unicode_ci NOT NULL;

# forumPost.body
#  * edit page: https://vagrant.dev.schoolbox.cloud/forum/modify/83/4/12
#  * Client-side length limit found and updated? YES
ALTER TABLE forumPost CHANGE COLUMN body body mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL;

# pastoral.body_private
#  * edit page: https://vagrant.dev.schoolbox.cloud/pastoral/student/25/record/2/update
#  * Client-side length limit found and updated? YES
ALTER TABLE pastoral CHANGE COLUMN body_private body_private mediumtext COLLATE utf8mb4_unicode_ci NOT NULL;

# pastoral.body_public
#  * edit page: https://vagrant.dev.schoolbox.cloud/pastoral/student/25/record/2/update
#  * Client-side length limit found and updated? YES
ALTER TABLE pastoral CHANGE COLUMN body_public body_public mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL;

# pastoral_revision.body_private
#  * edit page: Same as pastoral.body_private
#  * Client-side length limit found and updated? N/A (this field is not directly edited)
ALTER TABLE pastoral_revision CHANGE COLUMN body_private body_private mediumtext COLLATE utf8mb4_unicode_ci NOT NULL;

# pastoral_revision.body_public
#  * edit page:Same as pastoral.body_public
#  * Client-side length limit found and updated? N/A (this field is not directly edited)
ALTER TABLE pastoral_revision CHANGE COLUMN body_public body_public mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL;

# textBox.body
#  * edit page: https://vagrant.dev.schoolbox.cloud/homepage/88 (Modify 'Romeo and Juliet Learning Objectives).
#  * Client-side length limit found and updated? YES
ALTER TABLE textBox CHANGE COLUMN body body mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL;
