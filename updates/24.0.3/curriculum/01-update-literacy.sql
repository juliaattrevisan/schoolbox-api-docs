-- Change a "English > Year 7 > Literature" node to "Literacy"
-- Change a "English > Year 8 > Literature" node to "Literacy"
UPDATE curriculum_node
SET name = 'Literacy'
WHERE id IN (
    SELECT cn2.id
    FROM curriculum_node cn1
    INNER JOIN curriculum_node cn2 ON cn1.curriculum_node_parent_id = cn2.id
    INNER JOIN curriculum_node cn3 ON cn2.curriculum_node_parent_id = cn3.id
    INNER JOIN curriculum_node cn4 ON cn3.curriculum_node_parent_id = cn4.id
    INNER JOIN curriculum_node cn5 ON cn4.curriculum_node_parent_id = cn5.id
    WHERE cn1.name = 'Analysing, interpreting and evaluating'
    AND cn5.name = 'Australian Curriculum (v9)'
    AND cn2.name = 'Literature'
    AND cn3.name IN ('Year 7', 'Year 8')
)
LIMIT 2;