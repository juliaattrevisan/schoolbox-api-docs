ALTER TABLE curriculum_node
ADD enabled BOOL NOT NULL DEFAULT TRUE
AFTER description;
