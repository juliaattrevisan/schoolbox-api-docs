INSERT INTO config (
    `name`, `value`, title, description, field_id, type_id, editable, hidden, required
) VALUES (
    'sharepoint_tenant', '', 'Microsoft SharePoint Tenant', 'The tenant name for your SharePoint installation',
    '1', '10', 'true', 'false', 'false'
), (
    'sharepoint_sitepath', '', 'Microsoft SharePoint Site Path', 'The site path for your SharePoint installation, if you have one',
    '1', '10', 'true', 'false', 'false'
) ON DUPLICATE KEY UPDATE `name` = `name`;