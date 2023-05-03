# badge.body.
ALTER TABLE badge CHANGE COLUMN body body mediumtext COLLATE utf8mb4_unicode_ci NOT NULL;

# eportfolio_component_blog.body
ALTER TABLE eportfolio_component_blog CHANGE COLUMN body body mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL;

# etherpad_pad.body
ALTER TABLE etherpad_pad CHANGE COLUMN body body mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL;

# event.detail
ALTER TABLE event CHANGE COLUMN detail detail mediumtext COLLATE utf8mb4_unicode_ci NOT NULL;

# forumPost.body
ALTER TABLE forumPost CHANGE COLUMN body body mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL;

# pastoral.body_private
ALTER TABLE pastoral CHANGE COLUMN body_private body_private mediumtext COLLATE utf8mb4_unicode_ci NOT NULL;

# pastoral.body_public
ALTER TABLE pastoral CHANGE COLUMN body_public body_public mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL;

# pastoral_revision.body_private
ALTER TABLE pastoral_revision CHANGE COLUMN body_private body_private mediumtext COLLATE utf8mb4_unicode_ci NOT NULL;

# pastoral_revision.body_public
ALTER TABLE pastoral_revision CHANGE COLUMN body_public body_public mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL;

# textBox.body
ALTER TABLE textBox CHANGE COLUMN body body mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL;
