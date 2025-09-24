# The column moment._hash was deprecated, but still has a FK on storage.hash:
# this causes a warning in the storage cleanup tool.
# Remove the FK as the field has been deprecated.
ALTER TABLE moment DROP FOREIGN KEY fk_evidence_hash;
