# Virtual label, for indexing and sorting purpose only.
ALTER TABLE curriculum_node
    DROP IF EXISTS virtual_label,
    ADD virtual_label VARCHAR(128) AS (
        SUBSTRING(
            IF (
                code IS NULL OR code = '',
                name,
                CONCAT('[', code, '] ', name)
            )
            FROM 1 FOR 128
        )
    ) AFTER code,
    ADD INDEX curriculum_node_virtual_label_index (virtual_label)
