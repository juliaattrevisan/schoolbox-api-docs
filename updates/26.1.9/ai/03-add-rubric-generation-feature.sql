ALTER TABLE openai_log
    MODIFY feature ENUM (
        'chat',
        'lessonplan',
        'image',
        'differentiation',
        'dialogagent',
        'learningactivitycustomisation',
        'rubricgeneration',
        'aimoderationagent'
    ) NOT NULL DEFAULT 'chat';

ALTER TABLE ai_session
    MODIFY feature ENUM (
        'chat',
        'lessonplan',
        'image',
        'differentiation',
        'dialogagent',
        'learningactivitycustomisation',
        'rubricgeneration',
        'aimoderationagent'
    ) NOT NULL;
