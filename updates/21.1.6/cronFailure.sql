ALTER TABLE storage_transform
    modify state enum ('processing', 'done', 'failed', 'error') null;

