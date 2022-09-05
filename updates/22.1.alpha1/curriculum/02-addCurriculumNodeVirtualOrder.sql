# Virtual order column, for indexing and sorting purpose only.
# Ensures that:
# * nodes with a code are sorted before ones without a code
# * nodes with a code are sorted by that code
# * nodes without a code are sorted by their name
ALTER TABLE curriculum_node
    ADD virtual_order VARCHAR(128) AS (
        SUBSTRING(
            IF (
                code IS NULL OR code = '',
                CONCAT('1-', name),
                CONCAT('0-', code)
            )
            FROM 1 FOR 128
        )
    ) AFTER code,
    ADD INDEX curriculum_node_virtual_order_index (virtual_order)
