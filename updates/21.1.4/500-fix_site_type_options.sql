-- These are the real available values recognised by the codebase and interated systems
UPDATE `config` SET options = 'dev:stg:prod:trial' WHERE name = 'site_type';
