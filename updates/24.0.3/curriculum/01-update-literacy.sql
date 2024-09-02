-- Change a "English > Year 7 > Literature" node to "Literacy"
-- Change a "English > Year 8 > Literature" node to "Literacy"
SELECT
    cn1.id, cn1.curriculum_node_parent_id, cn1.name,
    cn2.id, cn2.curriculum_node_parent_id, cn2.name,
    cn3.id, cn3.curriculum_node_parent_id, cn3.name,
    cn4.id, cn4.curriculum_node_parent_id, cn4.name,
cn5.id, cn5.curriculum_node_parent_id, cn5.name
FROM curriculum_node cn1
INNER JOIN curriculum_node cn2 ON cn1.curriculum_node_parent_id = cn2.id
INNER JOIN curriculum_node cn3 ON cn2.curriculum_node_parent_id = cn3.id
INNER JOIN curriculum_node cn4 ON cn3.curriculum_node_parent_id = cn4.id
INNER JOIN curriculum_node cn5 ON cn4.curriculum_node_parent_id = cn5.id
WHERE cn1.name = 'Analysing, interpreting and evaluating'
AND cn5.name = 'Australian Curriculum (v9)'
AND cn2.name = 'Literature'
AND cn3.name IN ('Year 7', 'Year 8')

