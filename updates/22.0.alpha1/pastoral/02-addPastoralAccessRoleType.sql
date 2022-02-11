# Add a new role_type column, so that a role type may be selected directly.
# In practice, this will probably be used for students only.
ALTER TABLE pastoral_access ADD role_type ENUM ('guest', 'student', 'parent', 'staff') NULL AFTER role_id;

# Add to pastoral audience too
ALTER TABLE pastoral_audience ADD role_type ENUM ('guest', 'student', 'parent', 'staff') NULL AFTER role_id;
