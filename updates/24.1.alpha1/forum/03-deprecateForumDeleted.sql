alter table forumPost change deleted _deleted enum ('true', 'false') null;
alter table forumThread change deleted _deleted enum ('true', 'false') null;
