drop index idx_comms_news_article_fulltext on comms_news_article;
drop index `fulltext` on blog;
drop index `fulltext` on file;
drop index `fulltext` on forumPost;
drop index threadtitle on forumThread;
drop index `fulltext` on link;
drop index `fulltext` on eotc_event;
drop index `fulltext` on event;
drop index `fulltext` on assessment;
drop index `fulltext` on lesson_plan_field;
drop index `fulltext` on wikiRevision;
drop index `fulltext` on `groups`;
drop index `fulltext` on luCampus;
drop index `fulltext` on user;

# SQL to restore these indices, should it be required:
#
# create fulltext index idx_comms_news_article_fulltext
#     on comms_news_article (title, body, blurb);
#
# create fulltext index `fulltext`
#     on blog (heading, body);
#
# create fulltext index `fulltext`
#     on file (realname, description, comment);
#
# create fulltext index `fulltext`
#     on forumPost (body);
#
# create fulltext index threadtitle
#     on forumThread (title);
#
# create fulltext index `fulltext`
#     on link (url, description, comment);
#
# create fulltext index `fulltext`
#     on eotc_event (title, detail, location);
#
# create fulltext index `fulltext`
#     on event (title, detail, location);
#
# create fulltext index `fulltext`
#     on assessment (title, description);
#
# create fulltext index `fulltext`
#     on lesson_plan_field (title, body);
#
# create fulltext index `fulltext`
#     on wikiRevision (heading, body);
#
# create fulltext index `fulltext`
#     on `groups` (name);
#
# create fulltext index `fulltext`
#     on luCampus (name);
#
# create fulltext index `fulltext`
#     on user (username, title, firstname, lastname, alt_email);
