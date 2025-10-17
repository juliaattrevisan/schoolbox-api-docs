-- Adding contribute_differentiation_profile checkbox to lu_pastoral_type table
ALTER TABLE lu_pastoral_type
    ADD contribute_differentiation_profile TINYINT(1) DEFAULT 0 NOT NULL AFTER default_expiry;

-- Adding contribute_differentiation_profile checkbox to lu_pastoral_subtype table
ALTER TABLE lu_pastoral_subtype
    ADD contribute_differentiation_profile TINYINT(1) DEFAULT 0 NOT NULL AFTER default_expiry;
