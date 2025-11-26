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
        'You are an AI role playing a persona for educational inquiry.
Your primary responsibility is to help the learner deepen their understanding of the topic by exploring the persona’s perspective through dialogue and reflective questioning.

You are a guide, not an assessor.
Maintain the authenticity of the persona while supporting the learner to think critically and make connections.

Guidelines
        • Stay fully in character at all times. Speak, react and reason as the persona would based on their own world and knowledge.
        • Use background information provided to enrich responses with relevant contextual details such as events, customs, culture and personal experiences.
        • Avoid modern knowledge or references the persona would not realistically have unless instructed otherwise.
        • Keep communication age appropriate, positive and engaging.
        • Ask open and reflective questions to help the learner consider multiple viewpoints and analyse ideas.
        • If confusion arises, clarify gently while remaining in character.
        • Encourage curiosity and let the learner lead the direction of inquiry when possible.

Goal
Enable the learner to develop deeper curiosity, empathy, contextual understanding and reflective thinking through role based inquiry.

Respond in <language> only.
Provide assistance to the following helpfulness.

Persona:
<persona>

Interview Theme / Focus Topic:
<interview-theme>

Background Information:
<background>

Helpfulness:
<helpfulness>

Rules of Interaction
<rules>
',
        'system',
        NOW(),
        NOW(),
        null
    ),
    (
        3,
        'Projection Ideation',
        'Guide students in developing their own projects, supporting creativity, decision-making, and alignment with learning goals.',
        'You are an AI project-ideation coach.
Your primary responsibility is to help the learner design a project that aligns with the specified goals, resources, and expected outcomes, while encouraging creativity and ownership.

You are a supportive guide, not a grader or decision-maker.
Help the learner think through choices, explore possibilities, and build confidence in their ideas.

Guidelines
        • Ask open-ended questions that help the learner clarify their purpose, audience, and project direction.
        • Support planning by prompting the learner to consider scope, feasibility, required steps, and success measures.
        • Keep communication age-appropriate, positive, and inclusive.
        • Encourage creativity and allow the learner to lead decisions, do not impose a project on them.
        • Offer options and suggestions that fit the provided constraints.
        • If confusion arises, clarify gently while maintaining a supportive tone.

Goal
Enable the learner to design a project that is realistic, meaningful, and personally engaging. Balancing creativity with clear direction and achievable outcomes.

Respond in <language> only.
Provide assistance to the following helpfulness.

Project Scope:
<project_scope>

Available Resources:
<available_resources>

Expected Outcome / Deliverable:
<expected_outcome>

Suggested Options:
<suggested_options>

Helpfulness:
<helpfulness>

Rules of Interaction:
<rules>',
        'system',
        NOW(),
        NOW(),
        null
    ),
    (
        4,
        'Self-Reflection',
        'Assisted self-reflection helps students articulate their understanding, recognise growth, and set goals, giving teachers deeper insights into learning progress.',
        'You are an AI reflection guide.
Your primary responsibility is to help the learner think about their learning journey, recognise progress, and set clear and achievable goals.

You are a supportive partner in reflection.
You are not assessing or judging the student.
Encourage honesty, confidence and a focus on personal growth.

Guidelines
        • Ask thoughtful and open questions that help the learner explain what they have learned and how they have changed.
        • Encourage the learner to recognise strengths and name areas for improvement.
        • Support the learner to set meaningful next steps that are realistic and motivating.
        • Keep interactions positive, age appropriate and inclusive.
        • If the learner is unsure, prompt gently and offer supportive examples to guide thinking.
        • Always celebrate effort and growth.

Goal
Enable the learner to express their thinking clearly, reflect on progress, and take ownership of their next steps.

Respond in <language> only.
Provide assistance aligned to the specified depth of reflection and the following helpfulness.

Purpose of Reflection:
<purpose_of_reflection>

Focus Area:
<focus_area>

Reflection Timeline:
<reflection_timeline>

Depth of Reflection:
<depth_of_reflection>

Goal Setting Expectations:
<goal_setting_expectations>

Helpfulness:
<helpfulness>

Rules of Interaction:
<rules>',
        'system',
        NOW(),
        NOW(),
        null
    ),
    (
        5,
        'Socratic Tutor',
        'Socratic tutoring guides students through dialogue and questioning, strengthening reasoning and deepening subject understanding.',
        'You are an AI tutor helping a student learn.
Your primary responsibility is to teach and guide the learner toward the specified understanding outcome through questions, dialogue and reflection.

You are a supportive learning partner.
You are not grading or assessing the student.
Your role is to help the learner think, reason and apply what they know.

Guidelines
        • Prioritise depth of understanding over speed.
        • Use questions to prompt reasoning and encourage the learner to explain, justify and refine their ideas.
        • Balance curiosity and guidance. Let the learner arrive at conclusions through their own thinking.
        • Provide encouragement and scaffolding, keeping communication age appropriate and inclusive.
        • If the learner is unsure or makes an error, respond constructively and ask questions that guide them forward.
        • Adapt to the learner’s responses and adjust the level of challenge as needed.
        • If the learner demonstrates clear understanding, support them to summarise and close the discussion.

Goal
Enable the learner to deepen conceptual understanding, strengthen reasoning skills and build confidence through guided inquiry.

Respond in <language> only.
Provide assistance to the following helpfulness.

Understanding Outcome:
<understanding_outcome>

Discussion Lens:
<discussion_lens>

Success Criteria:
<success_criteria>

Helpfulness:
<helpfulness>

Rules of interaction:
<rules>',
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
        1,
        'Switch',
        'switch',
        'switch',
        'This is a switch which will impact with a YES/NO',
        '{"onText":"Yes","offText":"No","onPrompt":"Make sure you link sheep into every answer","offPrompt":"Make sure you link aliens into every answer"}',
        3,
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
        'Interview Theme/Focus Topic',
        'interview_theme',
        'textarea',
        'Describe what aspect of the character’s life, beliefs, or experiences students will explore.',
        '{}',
        2,
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
        3,
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
        'Expected Outcome/Deliverable',
        'expected_outcome',
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
    ),
    (
        5,
        'Discussion Lens',
        'discussion_lens',
        'textarea',
        'Describe the lens students apply (e.g. moral, scientific, literary)',
        '{}',
        2,
        NOW(),
        NOW(),
        null
    ),
    (
        5,
        'Success Criteria',
        'success_criteria',
        'textarea',
        'How would you like the agent to decide if the student as reached the understanding outcome? (e.g. explain in their own words, provide real world example. apply concept to a new problem)',
        '{}',
        3,
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
        'Effort rating',
        'Figure out how much effort they provided.',
        1,
        NOW(),
        NOW(),
        null
    ),
    (
        2,
        'Participation & Engagement',
        'Identify statements or questions that show how the student approached the activity (e.g., curiosity, sustained focus, attempts to clarify or extend understanding).

Note any indications of thoughtful engagement, such as the student making connections, asking meaningful or relevant questions, or showing persistence with the task.

Summarise, describing:
• the level of engagement demonstrated (e.g., exploratory, attentive, surface-level, purposeful)
• how the student interacted with the activity or the AI (e.g., seeking clarification, testing ideas, building on previous comments).',
        1,
        NOW(),
        NOW(),
        null
    ),
    (
        2,
        'Quality of Student Questions',
        'Identify the questions asked by the student.
Note whether questions were factual, interpretive, empathetic, or analytical.
Look for evidence of curiosity, relevance, or follow-up questioning that deepened the conversation.
Summarise, describing: (The overall focus or style of the student’s questioning. Any examples of probing or thoughtful questions. How their questions contributed to understanding the character’s experiences or perspective.)',
        2,
        NOW(),
        NOW(),
        null
    ),
    (
        2,
        'Understanding of Character’s Perspective',
        'Identify moments where the student acknowledged, interpreted, or responded to the character’s feelings, motivations, or beliefs.
Identify evidence of empathy, such as attempts to see the world through the character’s eyes or recognise their struggles, values, or decisions.
Note whether the student challenged or supported the character’s reasoning with sensitivity or understanding.
Summarise, describing: (The degree to which the student grasped the character’s perspective. How they demonstrated empathy or understanding. Any key insights or misinterpretations evident in their responses.)',
        3,
        NOW(),
        NOW(),
        null
    ),
    (
        2,
        'Critical Thinking & Reasoning',
        'Identify moments where the student linked the conversation to other knowledge sources — e.g., class readings, historical events, personal experiences, or current issues.
Identify examples of reasoning or comparison, such as drawing parallels, recognising patterns, or evaluating causes and effects.
Note how these connections supported or extended the student’s understanding of the character or topic.
Summarise, describing: (The types of connections the student made. How they reasoned through or applied those ideas. The overall relevance or insight gained from those links.)',
        4,
        NOW(),
        NOW(),
        null
    ),
    (
        2,
        'Connections to Learning Outcomes',
        'Read the Context Field to understand the curriculum focus, intended learning outcomes, or thematic framing set by the teacher.
Analyse the transcript for moments where the student showed understanding of these target ideas, including: Key curriculum concepts (e.g., characterisation, author intent, historical context, ethical reasoning), Links to prior knowledge, other texts, or earlier lessons & Real-world parallels or personal connections
Identify how the student interpreted the character, situation, or dialogue in relation to the specific context provided by the teacher.
Summarise, describing: ( How the student connected their discussion to the learning outcomes in the Context Field, The concepts, themes, or curriculum ideas they demonstrated understanding of & Any broader connections they made (e.g., to other texts, past learning, or lived experiences)',
        5,
        NOW(),
        NOW(),
        null
    ),
    (
        2,
        'Learning Debrief',
        'Identify any evidence that the student is analysing their learning, recognising challenges, consolidating concepts, or articulating what supported or hindered their progress.

Look for indicators of the student’s emerging strengths, developing skills, and areas where further support or guided practice may help them progress (e.g., concepts they are still exploring, strategies they are beginning to use, or reasoning that is forming but not yet secure).

Summarise, describing:
• insights the student shows about their current learning
• areas where additional scaffolding or next-step teaching may be beneficial (without evaluative or deficit language).',
        6,
        NOW(),
        NOW(),
        null
    ),
    (
        3,
        'Participation & Engagement',
        'Identify statements or questions that show how the student approached the activity (e.g., curiosity, sustained focus, attempts to clarify or extend understanding).

Note any indications of thoughtful engagement, such as the student making connections, asking meaningful or relevant questions, or showing persistence with the task.

Summarise, describing:
• the level of engagement demonstrated (e.g., exploratory, attentive, surface-level, purposeful)
• how the student interacted with the activity or the AI (e.g., seeking clarification, testing ideas, building on previous comments).',
        1,
        NOW(),
        NOW(),
        null
    ),
    (
        3,
        'Purpose & Relevance',
        'Review the transcript for explanations of why the student chose the topic or why the project is meaningful.
Identify any problems, audiences, or needs the student discussed.
Note references to personal interest, community impact, or real-world connections.
Summarise, describing: (The reason or motivation behind the project. The audience or issue it aims to address. How the student described its relevance or importance.)',
        2,
        NOW(),
        NOW(),
        null
    ),
    (
        3,
        'Process & Planning',
        'Review the transcript for any references to how the student intends to carry out their project — including tasks, sequence, or timeframes.
Identify mentions of materials, resources, or methods the student proposed.
Note if the student discussed timeline stages, such as research, creation, testing, or presentation.
Summarise, describing: (The main steps or plan the student outlined. Any methods or strategies they intend to use. Whether their planning appears structured or still developing.)',
        3,
        NOW(),
        NOW(),
        null
    ),
    (
        3,
        'Creative Thinking & Refinement',
        'Identify points in the transcript where the student brainstormed, generated alternatives, or reconsidered ideas.
Note how the AI’s prompts or feedback influenced the student’s creative development or scope refinement.
Look for evidence of the student evaluating different options, combining ideas, or clarifying focus.
Summarise, describing: (The ways the student used the AI to extend or improve their ideas. Any examples of creative problem-solving, comparison, or adaptation. How their concept evolved through discussion.)',
        4,
        NOW(),
        NOW(),
        null
    ),
    (
        3,
        'Project Next Steps',
        'Review statements about what the student intends to do next — actions, follow-up tasks, or project milestones.
Identify any mentions of resources, approvals, or guidance the student says they need (e.g., materials, teacher input, time, or collaboration).
Note if the AI helped the student define a short-term plan or next stage of work.
Summarise, describing: (The student’s intended next steps.Whether their plan appears specific or general at this stage.)',
        5,
        NOW(),
        NOW(),
        null
    ),
    (
        3,
        'Learning Debrief',
        'Identify any evidence that the student is analysing their learning, recognising challenges, consolidating concepts, or articulating what supported or hindered their progress.

Look for indicators of the student’s emerging strengths, developing skills, and areas where further support or guided practice may help them progress (e.g., concepts they are still exploring, strategies they are beginning to use, or reasoning that is forming but not yet secure).

Summarise, describing:
• insights the student shows about their current learning
• areas where additional scaffolding or next-step teaching may be beneficial (without evaluative or deficit language).',
        6,
        NOW(),
        NOW(),
        null
    ),
    (
        4,
        'Participation & Engagement',
        'Identify statements or questions that show how the student approached the activity (e.g., curiosity, sustained focus, attempts to clarify or extend understanding).

Note any indications of thoughtful engagement, such as the student making connections, asking meaningful or relevant questions, or showing persistence with the task.

Summarise, describing:
• the level of engagement demonstrated (e.g., exploratory, attentive, surface-level, purposeful, persistent,)
• how the student interacted with the activity or the AI (e.g., seeking clarification, testing ideas, building on previous comments).

Consider the tone (motivated, disengaged or uncertain)',
        1,
        NOW(),
        NOW(),
        null
    ),
    (
        4,
        'Self-Awareness',
        'Identify any statements that describe the student’s actions (what they did).
Identify any statements that describe the student’s feelings, emotions, or reflections about those actions.
Summarise, describing:
The main activity or experience the student referred to.
The feelings or self-awareness they expressed.
The degree of reflection (surface-level or deeper insight).',
        2,
        NOW(),
        NOW(),
        null
    ),
    (
        4,
        'Explanation of Thinking / Process',
        'Identify any statements that describe the student’s approach, method, or sequence of actions.
Note any reasoning or decision-making steps they mention (e.g. why they chose a strategy, what they changed, how they adapted).
Summarise, describing: (The task or challenge the student was working on. The strategies or steps they used to complete it.The level of insight shown in explaining their process (e.g. simple recount vs. thoughtful explanation).)',
        3,
        NOW(),
        NOW(),
        null
    ),
    (
        4,
        'Use of Feedback / Goal Setting',
        'Identify any references to feedback they received (from teachers, peers, or self-assessment).
Note how they describe using that feedback — whether they acknowledge it, apply it, or plan to use it.
Identify any goals or intentions for future improvement.
Summarise, describing: (What type of feedback the student discussed. How they responded to or applied it. Any goals or improvement actions they articulated.)',
        4,
        NOW(),
        NOW(),
        null
    ),
    (
        4,
        'Depth of Reflection ',
        'Identify evidence that the student is analysing causes, effects, or reasons behind their experiences or outcomes.
Look for signs of learning transfer or future planning (e.g. what they would do differently next time, how they can apply what they learned).
Summarise, describing: (The main insight or analysis the student provides. Any patterns, lessons, or reasoning they identify. How they connect reflection to future action or broader learning)',
        5,
        NOW(),
        NOW(),
        null
    ),
    (
        4,
        'Learning Debrief',
        'Identify any evidence that the student is analysing their learning, recognising challenges, consolidating concepts, or articulating what supported or hindered their progress.

Look for indicators of the student’s emerging strengths, developing skills, and areas where further support or guided practice may help them progress (e.g., concepts they are still exploring, strategies they are beginning to use, or reasoning that is forming but not yet secure).

Summarise, describing:
• insights the student shows about their current learning
• areas where additional scaffolding or next-step teaching may be beneficial (without evaluative or deficit language).',
        6,
        NOW(),
        NOW(),
        null
    ),
    (
        5,
        'Participation & Engagement',
        'Identify statements or questions that show how the student approached the activity (e.g., curiosity, sustained focus, attempts to clarify or extend understanding).

Note any indications of thoughtful engagement, such as the student making connections, asking meaningful or relevant questions, or showing persistence with the task.

Summarise, describing:
• the level of engagement demonstrated (e.g., exploratory, attentive, surface-level, purposeful)
• how the student interacted with the activity or the AI (e.g., seeking clarification, testing ideas, building on previous comments).',
        1,
        NOW(),
        NOW(),
        null
    ),
    (
        5,
        'Student’s Initial Understanding',
        'Identify what the student believed, assumed, or stated about the topic before deeper questioning occurred.
Look for keywords or reasoning that show their starting perspective, level of confidence, or misconceptions.
Summarise this, describing: (The main idea or belief the student expressed at the outset. Any reasoning or examples they used to justify it.)',
        2,
        NOW(),
        NOW(),
        null
    ),
    (
        5,
        'AI Prompting & Questioning Path',
        'Identify the types of questions the AI used to deepen the discussion — e.g. clarifying questions, hypothetical scenarios, comparisons, or challenges to assumptions.
Note whether the AI introduced counterexamples, evidence requests, or reasoning prompts that encouraged the student to think more critically.
Summarise this describing: (The questioning strategies or patterns used by the AI. How those prompts shaped or redirected the discussion. Any notable moments where the AI introduced alternative viewpoints or deeper inquiry.',
        3,
        NOW(),
        NOW(),
        null
    ),
    (
        5,
        'Student’s Reasoning Development',
        'Review the dialogue, focusing on the progression of the student’s responses across the conversation.
Identify evidence of the student reconsidering, refining, or expanding their initial ideas.
Note any new explanations, self-corrections, or links they make to prior knowledge, real-world examples, or alternative viewpoints.
Summarise the reasoning progression, describing: (How the student’s thinking shifted or deepened. The kinds of reasoning or connections that emerged. Whether their responses showed increased nuance, logic, or self-awareness.)',
        4,
        NOW(),
        NOW(),
        null
    ),
    (
        5,
        'Remaining Gaps',
        'Pay attention to the student’s final responses and the areas where their reasoning remained incomplete or unclear.
Identify any concepts the student struggled to explain, contradictions in reasoning, or questions left unresolved.
Look for signs that the AI attempted to prompt deeper thinking, but the student’s response stayed surface-level or uncertain.
Summarise the aspects of the concept that remain misunderstood or undeveloped. Any ongoing misconceptions, gaps, or areas for further inquiry. How the student might benefit from revisiting or extending the discussion.',
        5,
        NOW(),
        NOW(),
        null
    ),
    (
        5,
        'Learning Debrief',
        'Identify any evidence that the student is analysing their learning, recognising challenges, consolidating concepts, or articulating what supported or hindered their progress.

Look for indicators of the student’s emerging strengths, developing skills, and areas where further support or guided practice may help them progress (e.g., concepts they are still exploring, strategies they are beginning to use, or reasoning that is forming but not yet secure).

Summarise the the following:
• insights the student shows about their current learning
• areas where additional scaffolding or next-step teaching may be beneficial (without evaluative or deficit language).',
        6,
        NOW(),
        NOW(),
        null
    );
