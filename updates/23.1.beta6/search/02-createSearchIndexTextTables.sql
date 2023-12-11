# Basic and full text indices for the search index.
# Deletes cascade because the text and fulltext indices have no meaning without
# the base index itself.
create table search_index_text
(
    identifier varchar(64)  not null,
    text_index varchar(250) not null,
    constraint search_index_text_search_index_identifier_fk
        foreign key (identifier) references search_index (identifier)
            on delete cascade on update cascade
);
create index search_index_text_text_index_index
    on search_index_text (text_index);

create table search_index_fulltext
(
    identifier     varchar(64) not null,
    fulltext_index mediumtext  not null,
    constraint search_index_fulltext_search_index_identifier_fk
        foreign key (identifier) references search_index (identifier)
            on delete cascade on update cascade
);
create fulltext index search_index_fulltext_fulltext_index_index
    on search_index_fulltext (fulltext_index);
