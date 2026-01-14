-- This update creates an index on the is_enabled column
-- of the differentiation_profiles_config table to improve query performance.
create index idx_differentiation_profiles_config_is_enabled
    on differentiation_profiles_config (is_enabled);
