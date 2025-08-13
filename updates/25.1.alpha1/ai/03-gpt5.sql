UPDATE config
SET options = 'gpt-4o:gpt-4o-mini:gpt-4.1:gpt-4.1-mini:gpt-5:gpt-5-mini'
WHERE name = 'openai_text_model';

UPDATE config
SET options = 'gpt-4o:gpt-4o-mini:gpt-4.1:gpt-4.1-mini:gpt-5:gpt-5-mini'
WHERE name = 'openai_image_model';
