UPDATE config
 SET hidden =
     -- Visible if Canvas or CanvasPlus
     (IF((SELECT * FROM (SELECT COUNT(*) FROM config WHERE name = 'site_plan' AND value IN ('Canvas','CanvasPlus')) t1), 'false', 'true'))
WHERE name IN ('canvas_data2_secret', 'canvas_data2_client_id', 'canvas_url');
