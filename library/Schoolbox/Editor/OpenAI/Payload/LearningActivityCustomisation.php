<?php

namespace Schoolbox\Editor\OpenAI\Payload;

use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

class LearningActivityCustomisation
{
    /**
     * These three params control wildness and output length
     */
    private const float TEMPERATURE       = 0.7;
    private const float TOP_P             = 0.9;
    private const int   MAX_OUTPUT_TOKENS = 75000;

    /**
     * System prompt for the learning activity customisation tool.
     * [CONFIG_OPENAI_LEARNING_ACTIVITY_CUSTOMISATION_SYSTEM_PROMPT] - additional system prompt content from config
     */
    private const string PROMPT_SYSTEM_PERSONA = "
        You are a highly educated and skilled expert teacher who specializes in differentiating learning activities for students with diverse learning needs.
        You are an expert on the management, creation, and interpretation of Individual Learning Plans (ILPs), ensuring that student learning needs are systematically
        considered to improve overall learning outcomes. Your primary goal is to provide practical, actionable modification suggestions that a teacher can implement in
        a classroom or for a specific learning activity, aligning with best practices in inclusive education and the details included in the supplied differentiation profile
        with the aim of supporting the student's learning.

        I will provide you with two key pieces of information to achieve this:
        1. A student differentiation profile: which contains information about the student like their learning needs, strengths, goals and known strategies etc.
        2. A learning activity: including its name, description, details, and academic context ( Year level, class, course, unit of study, and linked curriculum).

        Your task is to provide two distinct outputs based on the supplied student differentiation profile and original learning activity:

        1. Suggested Considerations (teacher-facing):
        Provide a concise, structured list of recommended adjustments for this learning activity (prioritise 2–4 high-impact recommendations).

        You MUST format the recommendations as valid HTML using one ordered list, with an unordered list nested inside each numbered recommendation, exactly like this:

        <ol>
          <li>
            <strong>[Action-Oriented Summary Title]</strong>
            <ul>
              <li><strong>Recommended Adjustment:</strong> [Describe a practical action the teacher can take for this specific activity.]</li>
              <li><strong>Rationale:</strong> [Briefly explain why this adjustment supports the student, directly referencing relevant information from the student's differentiation profile.]</li>
            </ul>
          </li>
        </ol>

        *Section 1 Constraints:*
        - Return valid HTML only in this field. Do NOT use Markdown syntax, plain-text numbering, or hyphens to create the list.
        - Each recommendation MUST be an <li> within a single <ol>, with a nested <ul> containing exactly two <li> elements labelled <strong>Recommended Adjustment:</strong> and <strong>Rationale:</strong>.
        - Include only recommendations directly supported by the student's differentiation profile and relevant to this specific activity. Do not restate the entire profile, infer unstated learning needs, or provide generic advice.

        2. Suggested Learning Activity Update (student-facing):
        Provide a complete, revised version of the original learning activity instructions ready for immediate student use that seamlessly incorporates the recommended adjustments and scaffolding.

        *Section 2 Constraints:*
        - **STUDENT LEVEL ALIGNMENT & SCAFFOLDING (CRITICAL):** Calibrate the reading level, sentence length, vocabulary complexity, and visual layout directly to the reading/comprehension level stated in the student's differentiation profile. Explicitly integrate all individual scaffolding techniques mentioned in the profile (e.g., visual checklists, sentence starters, graphic organizer templates, step-by-step chunking, or audio/TTS reminders) directly into the activity instructions so the student can complete the task successfully.
        - Preserve the original learning intention, curriculum focus, essential requirements, and factual content.
        - Write directly to the student using a clear, encouraging, and supportive tone.
        - Do NOT reference the student's profile, diagnosis, accommodations, or reasons for modification.
        - Do NOT include any teacher-facing notes, headers, or rationale in this section.

        *HTML Safety Constraints:*
        - In the Suggested Considerations field, use only these HTML elements: <ol>, <ul>, <li>, and <strong>.
        - In the Suggested Learning Activity Update field, use only these HTML elements: <p>, <h2>, <h3>, <ol>, <ul>, <li>, <strong>, <em>, and <br>.
        - Do NOT include HTML attributes of any kind, including style, class, id, href, src, or event-handler attributes such as onclick.
        - Do NOT include any other HTML elements, including <script>, <style>, <iframe>, <object>, <embed>, <form>, <input>, <button>, <link>, <meta>, <svg>, or <math>.

        All output must be presented in a single JSON object including HTML.
        Adhere strictly to the following rules:
        1. Prioritize actionable, in-the-moment strategies.
        2. Be specific and concrete. Avoid generic advice.
        3. Do not invent or infer information that is not explicitly present in the provided student profile or learning activity description.
        4. Do not include any introductory phrases before the content of each JSON field. Begin the content directly.
        5. If you cannot provide suggestions, the corresponding JSON field must be an empty string.
        6. The final output must be a single, valid JSON object and nothing else.
        -------------------------------------------------------------------
        [CONFIG_OPENAI_LEARNING_ACTIVITY_CUSTOMISATION_SYSTEM_PROMPT]
    ";

    /**
     * User prompt for the learning activity customisation tool.
     * [STUDENT_PROFILE_JSON] - student differentiation profile as a JSON object
     * [YEAR_LEVEL] - year level of the learning activity
     * [CLASS_NAME] - class name of the learning activity
     * [COURSE_NAME] - course name of the learning activity
     * [UNIT_NAME] - unit name of the learning activity
     * [CURRICULUM_CONTENT] - curriculum content linked to the learning activity
     * [ACTIVITY_NAME] - name of the learning activity
     * [ACTIVITY_DESCRIPTION] - description of the learning activity
     * [ACTIVITY_ATTACHMENT] - attachment details of the learning activity
     * [STUDENT_YEAR_LEVEL] - year level of the student
     */
    private const string PROMPT_USER_PERSONA = "
        I will provide you with a student's differentiation profile and a learning activity description. Your task is to generate differentiation suggestions based on this information.

        Here is the student’s differentiation profile, formatted as a JSON object:
        [STUDENT_PROFILE_JSON]

        Here is the learning activity information:
            Year Level: [YEAR_LEVEL]
            Class Name: [CLASS_NAME]
            Course Name: [COURSE_NAME]
            Unit Name: [UNIT_NAME]
            Curriculum: [CURRICULUM_CONTENT]
            Learning Activity Name: [ACTIVITY_NAME]
            Learning Activity Description: [ACTIVITY_DESCRIPTION]
            Learning Activity Attachment: [ACTIVITY_ATTACHMENT]
            Student Year Level: [STUDENT_YEAR_LEVEL]

        Please provide your response in a single JSON object with the following fields:

        ```json
        {
            Suggested Considerations: [html formatted text],
            Suggested Learning Activity Update: [html formatted text]
        }
        ```
    ";

    private const array RESPONSE_FORMAT = [
        'type'   => 'json_schema',
        'name'   => 'fields_format',
        'schema' => [
            'type'                 => 'object',
            'properties'           => [
                'suggested_considerations'           => [
                    'type' => 'string',
                ],
                'suggested_learning_activity_update' => [
                    'type' => 'string',
                ],
            ],
            'required'             => [
                'suggested_considerations',
                'suggested_learning_activity_update',
            ],
            'additionalProperties' => false,
        ],
        'strict' => true,
    ];

    /**
     * @param string      $openAiEndpoint
     * @param string      $openAiModel
     * @param string      $azureDeploymentId
     * @param string      $azureDeploymentModel
     * @param string      $systemPrompt
     * @param string      $reasoningEffort The reasoning effort level from admin config
     * @param string      $textVerbosity The text verbosity level from admin config
     * @param IAssessable $assessable
     * @param User        $student
     * @param Folder      $course
     * @param string      $attachment
     * @param string      $differentiationProfile
     */
    public function __construct(
        private string $openAiEndpoint,
        private string $openAiModel,
        private string $azureDeploymentId,
        private string $azureDeploymentModel,
        private string $systemPrompt,
        private string $reasoningEffort,
        private string $textVerbosity,
        private IAssessable $assessable,
        private User $student,
        private Folder $course,
        private string $attachment,
        private string $differentiationProfile,
    ) {
    }

    public function generate(): array
    {
        $responseFormat = self::RESPONSE_FORMAT;

        /* Replacing the token with the config setting defined prompt
         */
        $systemPrompt = strtr(
            self::PROMPT_SYSTEM_PERSONA,
            [
                '[CONFIG_OPENAI_LEARNING_ACTIVITY_CUSTOMISATION_SYSTEM_PROMPT]' => $this->systemPrompt,
            ]
        );

        $userPrompt = strip_tags(strtr(
            self::PROMPT_USER_PERSONA,
            [
                '[STUDENT_PROFILE_JSON]' => $this->differentiationProfile,
                '[YEAR_LEVEL]' => implode(',', $this->assessable->getBaseContext()->getFolder()->getYearLevels()?->getPropertyValues('name') ?? []),
                '[CLASS_NAME]' => $this->assessable->getContext()->getFolder()->getName(),
                '[COURSE_NAME]' => $this->course->getName(),
                '[UNIT_NAME]' => $this->assessable->getContext()->getTitle(),
                '[CURRICULUM_CONTENT]' => implode(',', $this->assessable->getCurriculumNodes()->getPropertyValues('description') ?? []),
                '[ACTIVITY_NAME]' => $this->assessable->getTitle(),
                '[ACTIVITY_DESCRIPTION]' => $this->assessable->getDescription(),
                '[ACTIVITY_ATTACHMENT]' => $this->attachment,
                '[STUDENT_YEAR_LEVEL]' => $this->student->getYear()?->getName() ?? '',
            ]
        ));

        $payload = [
            'temperature' => self::TEMPERATURE,
            'top_p' => self::TOP_P,
            'max_output_tokens' => self::MAX_OUTPUT_TOKENS,
            'input' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
            'stream' => false,
            'text' => [
                'format' => $responseFormat,
            ],
        ];

        if ($this->textVerbosity !== Config::TEXT_VERBOSITY_OPTION_DEFAULT) {
            $payload['text']['verbosity'] = $this->textVerbosity;
        }

        if ($this->reasoningEffort !== Config::REASONING_EFFORT_OPTION_DEFAULT) {
            $payload['reasoning'] = ['effort' => $this->reasoningEffort];
        }

        return new PayloadFinaliser()->finalise(
            $payload,
            $this->openAiEndpoint,
            $this->openAiModel,
            $this->azureDeploymentId,
            $this->azureDeploymentModel,
        );
    }
}
