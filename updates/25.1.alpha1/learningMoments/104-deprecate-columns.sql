alter table moment
    change hash _hash varchar(100) charset ascii null;

alter table moment
    change filename _filename varchar(250) null;

alter table moment
    change type _type enum ('photo', 'text', 'upload', 'video', 'audio') default 'upload' null;

