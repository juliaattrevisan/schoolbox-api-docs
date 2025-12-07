ALTER TABLE openai_log
    MODIFY feature ENUM ('chat', 'lessonplan', 'image', 'differentiation', 'dialogagent', 'learningactivitycustomisation') NULL;

