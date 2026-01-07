-- Update Presidio configuration URLs based on site environment
-- Clear the values if site_environment is not set or recognized

-- Update presidio_analysis_host based on site_environment
SELECT @site_env := (SELECT value FROM config WHERE name = 'site_environment' LIMIT 1);

UPDATE config
SET value = CASE
        WHEN @site_env = 'client-hosted'
            THEN 'http://localhost:5002'
        WHEN @site_env = 'cloud-saas'
            THEN 'http://presidio-analyzer.schoolbox'
        ELSE ''  -- Clear value if site_environment is not set or recognized
    END
WHERE name = 'presidio_analysis_host';

-- Update presidio_anonymisation_host based on site_environment
UPDATE config
SET value = CASE
        WHEN @site_env = 'client-hosted'
            THEN 'http://localhost:5001'
        WHEN @site_env = 'cloud-saas'
            THEN 'http://presidio-anonymizer.schoolbox'
        ELSE ''  -- Clear value if site_environment is not set or recognized
    END
WHERE name = 'presidio_anonymisation_host';

