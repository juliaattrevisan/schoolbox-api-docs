UPDATE config
SET value = (
    CASE value
        WHEN 'Canvas'     THEN 'Canvas-Enhanced'
        WHEN 'CanvasPlus' THEN 'Canvas-Elite'
        WHEN 'Elite'      THEN 'Learning-Enhanced'
        WHEN 'Pro'        THEN 'Learning-Essentials'
        WHEN 'Portal'     THEN 'Community-Essentials'
        WHEN 'PortalPlus' THEN 'Community-Enhanced'
        WHEN 'Wellbeing'  THEN 'Community-Elite'
        ELSE ''
    END
)
WHERE name='site_plan';
