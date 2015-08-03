INSERT INTO config (
    `name`,
    `value`,
    title,
    description,
    field_id,
    type_id,
    editable,
    hidden,
    required
) VALUES (
    'external_sync_isams_batch_api_host',
    '',
    'External Sync iSAMS Batch API Host',
    'The hostname of your iSAMS Batch API provider',
    '1',
    '7',
    'false',
    'true',
    'false'
), (
    'external_sync_isams_batch_api_key',
    '',
    'External Sync iSAMS Batch API Key',
    'The Batch API key for your iSAMS Batch API provider. It must provide full access the following datasets: TeachingManager, TimetableManager, PupilManager, HRManager, SchoolManager.',
    '1',
    '7',
    'false',
    'true',
    'false'
)ON DUPLICATE KEY UPDATE `name` = `name`;
