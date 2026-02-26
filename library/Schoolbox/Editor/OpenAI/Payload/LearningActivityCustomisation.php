<?php

namespace Schoolbox\Editor\OpenAI\Payload;

use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\Editor\OpenAI\Helper;
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

        Your task is to provide two distinct outputs:
        1. Suggested Considerations: Provide a concise, high-level overview of the differentiation strategy and what the teacher should consider when differentiation the
           learning for this student for this specific task. Explain the rationale for your suggested modifications, directly linking them to the information provided to you
           in the students differentiation profile.
        2. Suggested Learning Activity Update: Provide a complete, rewritten version of the original learning activity. The rewritten activity should integrate the
           suggested modifications seamlessly into the instructions, making it ready for a teacher to use with the student. Do not refer to the student's profile or mention
           the differentiation in this rewritten section. Simply provide the new, modified instructions. For this section the audience is the STUDENT.

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
     * @param string      $openAiDeploymentId
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
        private string $openAiDeploymentId,
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

        /* OpenAI uses the model name from our ui.
         * Azure uses the Deployment ID instead.
         */
        $model = $this->openAiModel;
        if (Helper::ENDPOINT_TYPE_AZURE === $this->openAiEndpoint) {
            $model = $this->openAiDeploymentId;
        }

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
            'model' => $model,
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

        return new ModelParser()->filterPayload($payload, $model);
    }
}
