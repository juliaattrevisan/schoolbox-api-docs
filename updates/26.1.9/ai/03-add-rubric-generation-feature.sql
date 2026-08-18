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
