-- Add 'Pastoral Entry Tip' field to pastoral record settings page

alter table lu_pastoral_type
add entry_tip mediumtext CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' null;

alter table lu_pastoral_subtype
add entry_tip mediumtext CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci' null;
