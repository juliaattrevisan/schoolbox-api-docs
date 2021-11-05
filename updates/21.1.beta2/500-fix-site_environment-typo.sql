-- Fix typo ('sass' should be 'saas')
UPDATE config SET options = 'client-hosted:cloud-saas' WHERE name = 'site_environment';
