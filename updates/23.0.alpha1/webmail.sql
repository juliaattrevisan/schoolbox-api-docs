#depercrate webmail db columns

alter table menu_top
    change webmail _webmail tinyint default 0 null;

alter table `user`
    change webmail _webmail tinyint unsigned default 1 not null;

DELETE FROM ups_effect WHERE user_property = 'webmail';
