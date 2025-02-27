-- Allow default points entry to type/subtype to pastoral record settings page

alter table lu_pastoral_type
add default_points float default null after sentiment;

alter table lu_pastoral_subtype
add default_points float default null after sentiment;
