alter table forumPost add deleted_at datetime null after deleted;
alter table forumThread add deleted_at datetime null after deleted;
