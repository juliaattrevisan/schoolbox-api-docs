INSERT INTO config (
    `name`, `value`, title, description, field_id, type_id, editable, hidden, required
) VALUES (
    'office365_tenant', '', 'Microsoft Office 365 Tenant Name',  'The tenant name for your Office 365 installation',
    '1', '10', 'true', 'false', 'false'
), (
    'office365_sitepath', '', 'Microsoft Office 365 Site Path',  'The site path for your Office 365 installation (optional, only if configured)',
    '1', '10', 'true', 'false', 'false'
), (
    'office365_client', '', 'Microsoft Office 365 App ClientID', 'The client app ID for your Office 365 Schoolbox installation',
    '1', '10', 'true', 'false', 'false'
), (
    'office365_secret', '', 'Microsoft Office 365 App Secret',   'The client secret for your Office 365 Schoolbox installation',
    '1', '10', 'true', 'false', 'false'
) ON DUPLICATE KEY UPDATE `name` = `name`;