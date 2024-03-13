# New fields on mobile app for accommodating Firebase.
alter table mobile_app add public_configuration text null after `key`;
alter table mobile_app add web_push_public_key varchar(512) null after public_configuration;
