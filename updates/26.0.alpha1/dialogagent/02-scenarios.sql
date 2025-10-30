INSERT INTO dialog_agent_scenario
    (id, title, description, prompt_template, type, created_at, updated_at, deleted_at)
VALUES
    (
        1,
        'Test scenario',
        'This scenario is for dev testing only',
        'You are a AI agent designed to be a helpful tutor for students. Using the context from <text_area> educate students. <switch>. Make sure your responses are in this style - <dropdown>. Respond in <language> only.

        <helpfulness>',
        'custom',
        NOW(),
        NOW(),
        null
    ),
    (
        2,
        'Character Interview',
        'Let students explore perspectives and context through questioning a fictional or historical persona, deepening understanding and critical thinking in an engaging way.',
        '',
        'system',
        NOW(),
        NOW(),
        null
    ),
    (
        3,
        'Projection Ideation',
        'Guide students in developing their own projects, supporting creativity, decision-making, and alignment with learning goals.',
        '',
        'system',
        NOW(),
        NOW(),
        null
    ),
    (
        4,
        'Self-Reflection',
        'Assisted self-reflection helps students articulate their understanding, recognise growth, and set goals, giving teachers deeper insights into learning progress.',
        '',
        'system',
        NOW(),
        NOW(),
        null
    ),
    (
        5,
        'Socratic Tutor',
        'Socratic tutoring guides students through dialogue and questioning, strengthening reasoning and deepening subject understanding.',
        '',
        'system',
        NOW(),
        NOW(),
        null
    );

INSERT INTO dialog_agent_scenario_field
    (scenario_id, title, slug, type, meta, options, sequence, created_at, updated_at, deleted_at)
VALUES
    (
        1,
        'Text Area',
        'text_area',
        'textarea',
        'This is a text area for adding text',
        '{}',
        1,
        NOW(),
        NOW(),
        null
    ),
    (
        1,
        'Switch',
        'switch',
        'switch',
        'This is a switch which will impact with a YES/NO',
        '{"onText":"Yes","offText":"No","onPrompt":"Make sure you link sheep into every answer","offPrompt":"Make sure you link aliens into every answer"}',
        2,
        NOW(),
        NOW(),
        null
    ),
    (
        1,
        'Drop-down',
        'dropdown',
        'dropdown',
        'This dropdown option is used in the prompt',
        '{}',
        2,
        NOW(),
        NOW(),
        null
    ),
    (
        2,
        'Persona',
        'persona',
        'textarea',
        'Provide the context of the persona the students will be interacting with, fictional or real, name of person',
        '{}',
        1,
        NOW(),
        NOW(),
        null
    ),
    (
        2,
        'Background Information',
        'background_information',
        'textarea',
        'Any background information on the persona, set facts',
        '{}',
        2,
        NOW(),
        NOW(),
        null
    ),
    (
        3,
        'Project Scope',
        'project_scope',
        'textarea',
        'e.g time available, length of project, individual vs group, required format (presentation, report, prototype, etc.).',
        '{}',
        1,
        NOW(),
        NOW(),
        null
    ),
    (
        3,
        'Available Resources',
        'available_resources',
        'textarea',
        'What students can access (lab equipment, software, library, internet, etc.).',
        '{}',
        2,
        NOW(),
        NOW(),
        null
    ),
    (
        3,
        'Assessment Criteria',
        'assessment_criteria',
        'textarea',
        'What success looks like (creativity, research skills, application of knowledge, etc.).',
        '{}',
        3,
        NOW(),
        NOW(),
        null
    ),
    (
        3,
        'Suggested Options',
        'suggested_options',
        'textarea',
        'Optional “big ideas” or real-world contexts to narrow brainstorming. Examples or non-examples – to help the AI model the type of projects that are encouraged (and avoid off-track ideas).',
        '{}',
        4,
        NOW(),
        NOW(),
        null
    ),
    (
        4,
        'Purpose of Reflection',
        'purpose_of_reflection',
        'textarea',
        'Is it about consolidating content knowledge, evaluating skills, monitoring progress, or considering attitudes/behaviours?',
        '{}',
        1,
        NOW(),
        NOW(),
        null
    ),
    (
        4,
        'Focus Areas',
        'focus_areas',
        'textarea',
        'Specific skills, concepts, or competencies students should reflect on (e.g. problem-solving, collaboration, writing technique).',
        '{}',
        2,
        NOW(),
        NOW(),
        null
    ),
    (
        4,
        'Reflection Time-frame',
        'reflection_timeframe',
        'textarea',
        'Is this reflecting on a single lesson/activity, a unit, or cumulative learning?',
        '{}',
        3,
        NOW(),
        NOW(),
        null
    ),
    (
        4,
        'Depth of Reflection',
        'depth_of_reflection',
        'textarea',
        'Quick check-in (surface level) vs deeper metacognitive reflection (strategies, process, habits).',
        '{}',
        4,
        NOW(),
        NOW(),
        null
    ),
    (
        4,
        'Goal Setting Expectations',
        'goal_setting_expectations',
        'textarea',
        'Should students be encouraged to set next steps or just describe progress?',
        '{}',
        5,
        NOW(),
        NOW(),
        null
    ),
    (
        5,
        'Understanding Outcome',
        'understanding_outcome',
        'textarea',
        'The knowledge or comprehension the student should reach. Example: Student should understand how gravity affects objects moving vertically.',
        '{}',
        1,
        NOW(),
        NOW(),
        null
    )
;

INSERT INTO dialog_agent_scenario_criteria
    (scenario_id, title, prompt, sequence, created_at, updated_at, deleted_at)
VALUES
    (
        1,
        'Evidence of Understanding',
        'Find evidence that shows understanding identified in the context of the task.',
        1,
        NOW(),
        NOW(),
        null
    ),
    (
        1,
        'Attitude and Engagement',
        'Provide feedback on the students interaction - specifically reviewing the attitude the student used, response language and etiquette of conversation, use supporting evidence.',
        2,
        NOW(),
        NOW(),
        null
    );
