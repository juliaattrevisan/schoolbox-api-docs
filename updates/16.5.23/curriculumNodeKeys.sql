-- Drop the foreign key on learning levels, so that nodes can be saved without them.
-- (The learning area FK was dropped, but not learning levels?)
ALTER TABLE `curriculum_node` DROP FOREIGN KEY `curriculum_node_ibfk_3`;
