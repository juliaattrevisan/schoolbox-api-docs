alter table mobile_app
    add deleted_at datetime null;

alter table mobile_app_registry
    add deleted_at datetime null;

alter table mobile_app_registry_backup
    add deleted_at datetime null;
