-- Ensure that there's no duplicate resource/role permissions.
ALTER TABLE `resource_role_permission`
    ADD UNIQUE INDEX `resource_role_unique` (`resource_id`, `role_id`);
