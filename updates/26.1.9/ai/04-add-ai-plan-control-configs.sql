INSERT INTO config (
    name,
    title,
    value,
    description,
    field_id,
    type_id,
    editable,
    hidden,
    required,
    module,
    sequence
)
VALUES
    ('ai_text_chat_enabled', 'AI Text Chat Enabled', '1', 'Controls whether AI Text Chat is available for this plan.', (SELECT id FROM lu_config_field WHERE field = 'boolean'), (SELECT id FROM lu_config_type WHERE type = 'Modules'), 'false', 'true', 'false', 'settings', 0),
    ('ai_lessonplan_enabled', 'AI Lesson Plan Enabled', '1', 'Controls whether AI Lesson Plan is available for this plan.', (SELECT id FROM lu_config_field WHERE field = 'boolean'), (SELECT id FROM lu_config_type WHERE type = 'Modules'), 'false', 'true', 'false', 'settings', 0),
    ('ai_differentiation_enabled', 'AI Differentiation Enabled', '1', 'Controls whether AI Differentiation is available for this plan.', (SELECT id FROM lu_config_field WHERE field = 'boolean'), (SELECT id FROM lu_config_type WHERE type = 'Modules'), 'false', 'true', 'false', 'settings', 0),
    ('ai_dialogagent_enabled', 'AI Discussion Activity Enabled', '1', 'Controls whether AI Discussion Activity is available for this plan.', (SELECT id FROM lu_config_field WHERE field = 'boolean'), (SELECT id FROM lu_config_type WHERE type = 'Modules'), 'false', 'true', 'false', 'settings', 0),
    ('ai_image_generation_enabled', 'AI Image Generation Enabled', '1', 'Controls whether AI Image Generation is available for this plan.', (SELECT id FROM lu_config_field WHERE field = 'boolean'), (SELECT id FROM lu_config_type WHERE type = 'Modules'), 'false', 'true', 'false', 'settings', 0),
    ('ai_learning_activity_customisation_enabled', 'AI Learning Activity Customisation Enabled', '1', 'Controls whether AI Learning Activity Customisation is available for this plan.', (SELECT id FROM lu_config_field WHERE field = 'boolean'), (SELECT id FROM lu_config_type WHERE type = 'Modules'), 'false', 'true', 'false', 'settings', 0),
    ('ai_moderation_agent_enabled', 'AI Moderation Agent Enabled', '1', 'Controls whether the AI Moderation Agent runs for this plan.', (SELECT id FROM lu_config_field WHERE field = 'boolean'), (SELECT id FROM lu_config_type WHERE type = 'Modules'), 'false', 'true', 'false', 'settings', 0),
    ('ai_rubric_generation_enabled', 'AI Rubric Generation Enabled', '0', 'Controls whether AI Rubric Generation is available for this plan.', (SELECT id FROM lu_config_field WHERE field = 'boolean'), (SELECT id FROM lu_config_type WHERE type = 'Modules'), 'false', 'true', 'false', 'settings', 0)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    description = VALUES(description),
    field_id = VALUES(field_id),
    type_id = VALUES(type_id),
    editable = VALUES(editable),
    hidden = VALUES(hidden),
    required = VALUES(required),
    module = VALUES(module);
