alter table allowedFileTypes modify max_file_size bigint default 0 not null;
alter table storage modify filesize bigint default 0 not null;
