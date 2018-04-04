/**
 * Allow namespace slugs to be more specific.
 *
 * This requires a separate column, because msg_namespace_slug is a FK on msg_namespace.
 */
ALTER TABLE component_definition
    ADD COLUMN msg_namespace_slug_suffix VARCHAR(32) CHARACTER SET ascii DEFAULT NULL AFTER msg_namespace_slug;
