create index user_email
    on user (alt_email);

create index user_key
    on user (`key`);
