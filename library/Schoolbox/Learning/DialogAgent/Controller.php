<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\DialogAgent;

use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Psr\Http\Client\ClientExceptionInterface;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Ramsey\Uuid\Uuid;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Mapper as DialogAgentDataModelMapper;
use Schoolbox\Admin\DialogAgent\Scenario\Mapper as ScenarioMapper;
use Schoolbox\Admin\DialogAgent\Scenario\Field\Context\Mapper as FieldContextMapper;
use Schoolbox\Admin\DialogAgent\Scenario\Criteria\Mapper as CriteriaMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Editor\OpenAI\Log\LogFormat as AiLogFormat;
use Schoolbox\Editor\OpenAI\Log\Mapper as AILogMapper;
use Schoolbox\Editor\OpenAI\Log\Translator as AILogTranslator;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model as DialogAgentDataModel;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DialogAgent\Lookup\Language;
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgent;
use Schoolbox\Learning\Assessment\DialogAgent\Response\MapperProxy as DialogAgentResponseMapper;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Model as Response;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Status;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Translator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\OpenAi;
use Schoolbox\User;
use Twig\Error\Error;

/**
 * Put activity-specific endpoints here. This \Schoolbox\Learning\*\Controller class is intended for endpoints
 * that are outside the canonical path suggested by \Schoolbox\Assessment\Controller so you can manage the unique
 * and nuanced behaviours of your learning activity without exposing it to every other learning activity and break
 * away from the inheritance tree of \Schoolbox\Assessment\Controller.
 */
class Controller extends Base
{
    /**
     * Constructor.
     *
     * @param BreadcrumbFactory $bakery
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param OpenAI\ClientFactory $aiClientFactory
     * @param AILogMapper $aiLogMapper
     * @param DialogAgentDataModelMapper $dialogAgentDataModelMapper
     * @param DialogAgentResponseMapper $dialogAgentResponseMapper
     * @param ScenarioMapper $scenarioMapper
     * @param FieldContextMapper $fieldContextMapper
     * @param CriteriaMapper $criteriaMapper
     */
    public function __construct(
        private BreadcrumbFactory $bakery,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private OpenAI\ClientFactory $aiClientFactory,
        private AILogMapper $aiLogMapper,
        private DialogAgentDataModelMapper $dialogAgentDataModelMapper,
        private DialogAgentResponseMapper $dialogAgentResponseMapper,
        private ScenarioMapper $scenarioMapper,
        private FieldContextMapper $fieldContextMapper,
        private CriteriaMapper $criteriaMapper,
    ) {
    }


    /**
     * Configure the dialog agent's settings
     *
     * @param DialogAgent $assessment
     * @return void
     * @throws Deleted
     * @throws Exception
     * @throws RouteNotFound
     * @throws Error
     */
    public function getUpdateForm(DialogAgent $assessment): void
    {
        /* Breadcrumbs
         */
        $breadcrumbs = $this->bakery->produceFromAssessment($assessment, $this->getUser())
            ->add([
                $assessment->getTitle(),
                $assessment->isImplementation() ? $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]) : null
            ])
            ->add(['Configure', null]);

        /* We need the dialog_agent.id for a schematic join. This follows a previous phenomenon where quiz
         * also had to be fetched separately to its assessment instance. A bit frustrating to need that,
         * but sub-object's fields don't really surface through the domain model for a reason.
         */
        $dialogAgent = $this->dialogAgentDataModelMapper->findOneByColumn('assessment_id', $assessment->getId());

        $fieldContexts = $this->fieldContextMapper->findByColumn('dialog_agent_id', $dialogAgent->getId());

        $scenario = $this->scenarioMapper->findById($assessment->getScenarioId());

        $data = [
            'assessment' => $assessment,
            'breadcrumbs' => $breadcrumbs,
            'scenario' => [
                'title' => $scenario->getTitle(),
                'description' => $scenario->getDescription(),
            ],
            'assessmentConfig' => [
                'helpfulness' => $dialogAgent->getHelpfulness(),
                'language' => $dialogAgent->getLanguage(),
                'openingMessage' => $dialogAgent->getOpeningMessage(),
                'rules' => $dialogAgent->getRules(),
                'useDifferentiationProfile' => $dialogAgent->getUseDifferentiationProfile(),
                'fieldContexts' => $fieldContexts->map(fn ($fieldContext) => [
                    'id' => $fieldContext->getId(),
                    'body' => $fieldContext->getBody(),
                    'field' => [
                        'id' => $fieldContext->getField()->getId(),
                        'title' => $fieldContext->getField()->getTitle(),
                    ],
                ])->reindex(),
                '_routes' => [
                    'postSandboxMessage' => $this->getRouteIfAuthorized('learningDialogAgent.postSandboxMessage', [
                        'assessmentId' => $assessment->getId(),
                        'assessment' => $assessment,
                    ]),
                ],
            ],
            'helpfulnessOptions' => [
                DialogAgentDataModel::HELPFULNESS_MINIMAL => 'Minimal',
                DialogAgentDataModel::HELPFULNESS_MODERATE => 'Moderate',
                DialogAgentDataModel::HELPFULNESS_EXTREME => 'Extreme',
            ],
            'languageOptions' => Language::AVAILABLE,
        ];

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/dialogAgent/configure.twig',
            $data,
            \sprintf('Configure %s', $assessment->getTitle())
        );
    }


    /**
     * Update the configuration of a dialog agent and persist scenario field context bodies
     *
     * @param DialogAgent $assessment
     * @return void
     * @throws Deleted
     * @throws Exception
     * @throws RouteNotFound
     */
    public function postUpdate(DialogAgent $assessment): void
    {
        $postArray = $this->requestPost->getArrayCopy();

        $assessment->setHelpfulness($postArray['helpfulness'])
            ->setLanguage($postArray['language'])
            ->setOpeningMessage($postArray['openingMessage'])
            ->setRules($postArray['rules'])
            ->setUseDifferentiationProfile((bool)($postArray['useDifferentiationProfile'] ?? false));

        $this->assessmentMapperProxy->save($assessment, $this->getUser());

        /* We need the dialog_agent.id for a schematic join. This follows a previous phenomenon where quiz
         * also had to be fetched separately to its assessment instance. A bit frustrating to need that,
         * but sub-object's fields don't really surface through the domain model for a reason.
         */
        $dialogAgentId = $this->dialogAgentDataModelMapper->findOneByColumn('assessment_id', $assessment->getId())->getId();

        /* Each field in the scenario should be populated with the value provided in the ui
         *
         * First find out if scenario field contexts already exist; if so, update and reuse them
         * Otherwise spawn new instances upon first save
         */
        $existingFieldContexts = $this->fieldContextMapper->findByColumn('dialog_agent_id', $dialogAgentId)->indexBy('id');

        foreach ($existingFieldContexts as $fieldContextId => $fieldContext) {
            $existingFieldContexts[$fieldContextId]->setBody($postArray['fieldContexts'][$fieldContextId]);
        }

        /* I'll save them. I'll save them all.
         *    --Jardani Jovonovich
         */
        $this->fieldContextMapper->save($existingFieldContexts);

        /* Produce the prompt
         * In the future this will actually go and generate another prompt as an "improvement" to compare against,
         * but that isn't in scope for 26.0 apparently.
         *
         * TODO: split this out into something reusable I guess (and make tokens somewhat less arbitrary)
         */
        $fieldReplacements = [
            '<opening_message>' => $assessment->getOpeningMessage(),
            '<rules>' => $assessment->getRules(),
            '<language>' => $assessment->getLanguage(),
        ];
        foreach ($existingFieldContexts as $fieldContextId => $fieldContext) {
            $fieldPlaceholder = \sprintf('<%s>', $fieldContext->getField()->getSlug());
            $fieldReplacements[$fieldPlaceholder] = $fieldContext->getBody();
        }

        $scenario = $this->scenarioMapper->findById($assessment->getScenarioId());
        $assessment->setPrompt(strtr($scenario->getPromptTemplate(), $fieldReplacements));

        $this->assessmentMapperProxy->save($assessment, $this->getUser());

        /* TODO Reset the chat here
         */

        $this->response = $this->responseAsJson([
            '_routes' => [
                'postSandboxMessage' => $this->getRouteIfAuthorized('learningDialogAgent.postSandboxMessage', [
                    'assessmentId' => $assessment->getId(),
                    'assessment' => $assessment,
                ]),
            ],
        ]);
    }


    /**
     * View a response's chat log in a readonly mode
     *
     * @param DialogAgent $assessment
     * @param User $student
     * @param Response $response
     * @return void
     */
    public function getParticularResponse(DialogAgent $assessment, User $student, Response $response): void
    {
        $participant = $assessment->getParticipant($student);

        /* Prepare a redirection url if one was provided
         * This will be used after any actions are made (i.e. resetting the chat/submitting the response/etc)
         * If not provided, no redirection will occur and the page will just reload
         */
        $returnUrl = null;
        if ($this->requestQuery->get(Redirect::RETURN_URL)) {
            $returnUrl = Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL));
        }

        /**
         * Build up the existing feed from the logs.
         * Also add extra messages such as the opening message as if they were real messages if available
         */
        $feed = new \Schoolbox\Collection();

        $feed->add([
            'type' => 'response',
            'data' => [
                'status' => 'success',
                'responseId' => null,
                'image' => null,
                'message' => $assessment->getOpeningMessage()
            ],
        ]);

        if ($response->getSessionId()) {
            $logEntries = $this->aiLogMapper->findByColumn('session_id', $response->getSessionId(), [
                'order' => [ 'created_at' => 'ASC' ]
            ]);

            /** The strategy should always be AgentChat for each log */
            $logStrategyFactory = new AILogTranslator\Format\StrategyFactory();
            foreach ($logEntries as $log) {
                $logFormat = AILogFormat::from($log->getLogFormat());
                $strategy = $logStrategyFactory->makeStrategy($logFormat);
                $feed->add($strategy->translate($log));
            }

            // TODO: assessment data shouldn't be log data. There's a task to pull that apart somewhere
            // TODO: Inject the guardrails warning in the middle of that where appropriate and if the user has access to see it
        }

        $userTranslator = new User\Translator\Author\ModelToArray();
        $responseLinksTranslator = new Translator\Links($this->routeGenerator, $this->getAuthorizer(), $this->getUser());
        $responseTranslator = new Translator\ModelToArray($responseLinksTranslator);

        $data = [
            'breadcrumbs' => $this->bakery->produceFromAssessment($assessment, $this->getUser())->add([
                $assessment->getTitle(),
                $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]),
            ])->add(['Response']),
            'assessment' => $assessment,
            'feed' => $feed,
            'student' => $userTranslator->__invoke($student),
            'response' => $response,
            'responseData' => $responseTranslator->__invoke($response),
            'interactionLocked' => false, // TODO
            'returnUrl' => $returnUrl,
        ];

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/dialogAgent/response/view.twig',
            $data,
            \sprintf(
                'Response for %s in %s',
                $student->getFullName(),
                $assessment->getTitle(),
            )
        );
    }


    /**
     * Send a message to the chat agent for a particular response and stream the reply
     *
     * @param DialogAgent $assessment
     * @param User $student
     * @param Response $response
     * @throws ClientExceptionInterface
     * @return void
     */
    public function postMessage(DialogAgent $assessment, User $student, Response $response): void
    {
        // No BTTF in this space
        $this->setAllowBackToTheFuture(false);

        // ai Config
        $aiConfig = new \Schoolbox\Admin\OpenAI\Config();
        $agentConfig = $aiConfig->decode((string)$this->getConfigLoader()->get(ConfigMapper::NAME_AI_DIALOGAGENT));

        /**
         * For dialog agent response chats, the sessionID guid is directly tied to the response,
         * If the student has never sent a message before, a new session ID should be created
         */
        $aiSessionId = $response->getSessionId();
        if (null === $aiSessionId) {
            $aiSessionId = Uuid::uuid4()->toString();
            $response->setSessionId($aiSessionId);

            /**
             * The first message represents the "start" of a response, so you can have a draft response but it still be
             * in the "incomplete" state until the first message is sent, then it progresses to "in progress".
             *
             * You must end up here at least once per response (when the first message is sent)
             */
            $response->setStartedAt(\Alaress_Date_Format::now());
        }

        $agent = new OpenAI\Agent\DialogAgentChat(
            $this->aiClientFactory->buildForFeature(\Schoolbox\Editor\OpenAI\Helper::FEATURE_DIALOGAGENT),
            $agentConfig,
        );

        try {
            $stream = $agent->requestStream(
                $this->getUser(),
                $assessment->getPrompt(), // TODO: this is nullable, and a requirement to be able to chat... I expect you shouldn't even be able to get to this endpoint if it's not ready yet
                $this->requestPost->get('userPrompt'),
                $aiSessionId,
                previousResponseId: $this->requestPost->get('previousResponseId') ?? null,
            );

            /**
             * We must wait until now to save, as the log must exist
             * for the session ID foreign key constraint to be satisfied
             */
            $response
                ->setUpdatedBy($this->getUser()->getId())
                ->setUpdatedAt(\Alaress_Date_Format::now());
            $this->dialogAgentResponseMapper->save($response);

            // The iterable response can just be passed straight to responseAsEventStream
            $this->response = $this->responseAsEventStream($stream);
            return;
        } catch (SchoolboxException $e) {
            // TODO: when does this actually throw

            // This will always be a stream
            // Unfortunately the exception isn't raised until the response
            // is output, which doesn't happen until after this method
            // returns.
            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus($e->getCode())
                ->asArray();

            $this->response = $this
                ->responseAsJson($problem)
                ->withStatus($e->getCode());
            return;
        }
    }


    /**
     * Send a message to the chat agent for a particular assessment (configuration sandbox) and stream the reply
     *
     * @param DialogAgent $assessment
     * @return void
     */
    public function postSandboxMessage(DialogAgent $assessment): void
    {
        // No BTTF in this space
        $this->setAllowBackToTheFuture(false);

        // ai Config
        $aiConfig = new \Schoolbox\Admin\OpenAI\Config();
        $agentConfig = $aiConfig->decode((string)$this->getConfigLoader()->get(ConfigMapper::NAME_AI_DIALOGAGENT));

        /**
         * For dialog agent sandbox chats, the sessionID guid is provided by the frontend,
         * it needs to be reset each time the page is reloaded or the sandbox is reset.
         */
        $aiSessionId = $this->requestPost->get('aiSessionId');
        if (!$aiSessionId || !Uuid::isValid($aiSessionId)) {
            $problem = new ApiProblem("AI Session id is required and must be a valid UUID.")
                ->setStatus(400)
                ->asArray();

            $this->response = $this->responseAsJson($problem)
                ->withStatus(400);

            return;
        }

        $agent = new OpenAI\Agent\DialogAgentChat(
            $this->aiClientFactory->buildForFeature(\Schoolbox\Editor\OpenAI\Helper::FEATURE_DIALOGAGENT),
            $agentConfig,
        );

        try {
            $stream = $agent->requestStream(
                $this->getUser(),
                $assessment->getPrompt(), // TODO: this is nullable, and a requirement to be able to chat... I expect you shouldn't even be able to get to this endpoint if it's not ready yet
                $this->requestPost->get('userPrompt'),
                $aiSessionId,
                previousResponseId: $this->requestPost->get('previousResponseId') ?? null,
            );

            // The iterable response can just be passed straight to responseAsEventStream
            $this->response = $this->responseAsEventStream($stream);
            return;
        } catch (SchoolboxException $e) {
            // TODO: when does this actually throw

            // This will always be a stream
            // Unfortunately the exception isn't raised until the response
            // is output, which doesn't happen until after this method
            // returns.
            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus($e->getCode())
                ->asArray();

            $this->response = $this
                ->responseAsJson($problem)
                ->withStatus($e->getCode());
            return;
        }
    }


    /**
     * The current user requested assistance on a particular response. This applies only to authoritative responses:
     * you can't resolve assistance on non-authoritative responses.
     *
     * @param IAssessable $assessment
     * @param User $student
     * @param Response $response
     * @return void
     */
    public function postAssistanceRequest(IAssessable $assessment, User $student, Response $response): void
    {
        $response->setAssistanceRequestedAt(\Alaress_Date_Format::now());

        $this->dialogAgentResponseMapper->save($response);

        /** Respond as JSON with a consistent translated response data */
        $this->getStatus($response);
    }


    /**
     * The teacher helped the student and that student no longer requires assistance. This applies only to authoritative
     * responses: you can't resolve assistance on non-authoritative responses.
     *
     * @param IAssessable $assessment
     * @param User $student
     * @param Response $response
     * @return void
     */
    public function postAssistanceResolve(IAssessable $assessment, User $student, Response $response): void
    {
        $response->setAssistanceRequestedAt(null);

        $this->dialogAgentResponseMapper->save($response);

        /** Respond as JSON with a consistent translated response data */
        $this->getStatus($response);
    }


    /**
     * Either a student hits the submit button to finalise their dialog agent discussion,
     * or an instructor forcibly finalises a response on behalf of the student.
     *
     * @param IAssessable $assessment
     * @param User $student
     * @param Response $response
     * @return void
     */
    public function postSubmit(IAssessable $assessment, User $student, Response $response): void
    {
        $response
            ->setSubmittedAt(\Alaress_Date_Format::now())
            ->setSubmittedOnBehalfOf($student)
            ->setSubmittedBy($this->getUser());

        $this->dialogAgentResponseMapper->save($response);

        /** Respond as JSON with a consistent translated response data */
        $this->getStatus($response);
    }


    /**
     * Reopening a dialog agent for a particular student basically means "unsubmit this student's authoritative response"
     * The responseId provided must be the student's authoritative response for it to be reopened.
     *
     * @param IAssessable $assessment
     * @param User $student
     * @param Response $response
     * @return void
     */
    public function postReopen(IAssessable $assessment, User $student, Response $response): void
    {
        $response
            ->setSubmittedAt(null)
            ->setSubmittedBy(null);

        $this->dialogAgentResponseMapper->save($response);

        /** Respond as JSON with a consistent translated response data */
        $this->getStatus($response);
    }


    /**
     * Resetting a dialog agent for a particular student creates a new authoritative response (draft)
     * for the student to fill in, letting them begin again from scratch. This can only be done on
     * a currently in-progress authoritative response, you can't reset from the submitted or incomplete state.
     *
     * The responseId provided must be the student's authoritative response for it to be reset.
     *
     * @param IAssessable $assessment
     * @param User $student
     * @param Response $response
     * @return void
     * @throws Deleted
     * @throws Exception
     */
    public function postReset(IAssessable $assessment, User $student, Response $response): void
    {
        /* Reset the existing response to be non-authoritative
         */
        $response->setAuthoritative(false);
        $this->dialogAgentResponseMapper->save($response);

        /* Spawn a new one, using the latest dialog agent configuration which may differ to what $response had
         */
        $newResponse = $this->dialogAgentResponseMapper->createModel()
            ->setAssessable($assessment)
            ->setDialogAgent($this->dialogAgentDataModelMapper->findOneByColumn('assessment_id', $assessment->getId()))
            ->setSubmittedOnBehalfOf($student)
            ->setCreatedBy($student->getId())
            ->setUpdatedBy($student->getId())
            ->setAuthoritative(true);

        $this->dialogAgentResponseMapper->save($newResponse);

        /** Respond as JSON with a consistent translated response data */
        $this->getStatus($newResponse);
    }


    /**
     * Provides a json payload conversion of $response including routes/status
     * Used for asyncronous calls when the response state may have changed
     *
     * @param Response $response
     * @return void
     */
    public function getStatus(Response $response): void
    {
        $responseLinksTranslator = new Translator\Links($this->routeGenerator, $this->getAuthorizer(), $this->getUser());
        $responseTranslator = new Translator\ModelToArray($responseLinksTranslator);

        $this->response = $this->responseAsJson([
            'response' => $responseTranslator->__invoke($response),
        ])->withStatus(200);
    }


    /**
     * Export the status of everyone's Response(s) and Feedback(s) for the given assessment.
     *
     * @param DialogAgent $assessment
     * @return void
     */
    public function getExport(DialogAgent $assessment): void
    {
        /* No selections specified = all statuses allowed, normally; but I'm not sure it truly applies in this case.
         *
         * When all filters were activated, such as when you first visit Staus screen, &status is never sent,
         * causing that to be null. That should default to Status:STATUSES
         *
         * When all filters were deactivated, the grid in the user interface is empty, but this also means &status is
         * never sent, causing it to also be null. This will default to Status::STATUSES too. This means you'll get an
         * unexpected output when you've turned off all the filters and then exported to csv, but I don't understand why
         * you'd want to generate an empty csv: most of the time you'd have at least one of them selected.
         */
        $requestedStatuses = [];

        foreach ($this->requestQuery->get('status') ?? [] as $requestedStatus) {
            try {
                $requestedStatuses[] = Status::from($requestedStatus);
            } catch (\ValueError $e) {
                // invalid status: skip it
            }
        }

        if (!\count($requestedStatuses)) {
            $requestedStatuses = Status::cases();
        }

        /* Filter output to whatever was chosen in the frontend.
         */
        $requestedStatuses = array_fill_keys(array_map(static function ($a) {
            return $a->value;
        }, $requestedStatuses), true);

        /* Set up a file pointer
         */
        $fp = fopen('php://temp', 'w');

        /* Compile scenario field names as header elements
         */
        $criteria = $this->criteriaMapper->findByColumn('scenario_id', $assessment->getScenarioId());

        $fields = [];

        foreach ($criteria as $criteriaField) {
            $fields[(string)$criteriaField->getId()] = $criteriaField->getTitle();
        }

        /* Progressively assemble
         */
        fputcsv($fp, array_merge([
            'User ID',
            'Student External ID',
            'Student First Name',
            'Student Last Name',
            'Status'
        ], array_values($fields)), escape: '');

        /* Temporarily implemented to match user experience in the ui
         *
         * An exit-early if no filters were selected; you only need to show their name in this edgey usage.
         *
         * We don't need to compute the outcome for "no status selected", which makes me wonder why we even output the
         * header rows at all; but this aligns with what humans see in the user interface if they turned everything off.
         */
        if (\count($requestedStatuses)) {
            /* A reusable struct where we associate criteria ids with the feedback comment
             * That's the stuff generated by AI
             */
            $feedbackFieldsTemplate = array_keys($fields);

            /* Compile everyone's authoritative response status and authoritative feedback content
             */
            foreach ($assessment->getAllParticipants() as $participant) {
                /* An authoritative response might not even exist; you'd be Incomplete.
                 * Or, an authoritative response exists, but not an authoritative feedback; you're In Progress.
                 */
                $authoritativeResponse = $participant->getAuthoritativeResponse(true);

                /** @var \Schoolbox\Learning\Assessment\DialogAgent\Feedback\Model|null $authoritativeFeedback */
                $authoritativeFeedback = $participant->getAuthoritativeFeedback(true);

                /* Check the status of this response and ensure it matches the ones provided in the querystring param
                 */
                /** @var \Schoolbox\Learning\Assessment\DialogAgent\Response\Model|null $response */
                $response = $authoritativeFeedback?->getResponse() ?? $authoritativeResponse;

                $currentStatus = $response?->getCurrentStatus()->value;

                if (!($requestedStatuses[$currentStatus] ?? false)) {
                    continue;
                }

                /* Make the same number of elements as $fields for now; these will eventually have feedback criteria in them
                 * but they should correspond 1:1 with $fields that we defined above
                 */
                $feedbackFields = array_fill_keys($feedbackFieldsTemplate, null);

                /* Compute outcomes for each criterion
                 */
                if ($authoritativeFeedback) {
                    foreach ($authoritativeFeedback->getCriteria() as $feedbackCriteria) {
                        $feedbackFields[$feedbackCriteria->getCriteriaId()] = $feedbackCriteria->getBody();
                    }
                }

                /* Slam them all together if they're part of the requested set of states
                 */
                fputcsv($fp, array_merge([
                    $participant->getUser()->getId(),
                    $participant->getUser()->getExternalId(),
                    $participant->getUser()->getFirstName(),
                    $participant->getUser()->getLastName(),
                    $response->getCurrentStatusHR(),
                ], array_values($feedbackFields)), escape: '');
            }
        }

        /* Rewind before obtaining stream contents
         */
        rewind($fp);

        $contents = stream_get_contents($fp);

        fclose($fp);

        /* Manufacture outcome format
         */
        $filename = sprintf(
            'discussion_%s_status_%s.csv',
            $assessment->getId(),
            \Alaress_Date_Format::now()->getDatabase()
        );

        $this->response = $this->responseFromContent($contents)
            ->withHeader('Pragma', ' ')
            ->withHeader('Cache-Control', ' ')
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Length', (string)\strlen($contents))
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
        ;
    }


    /**
     * Generate feedback for a particular dialog agent response
     *
     * @param DialogAgent $assessment
     * @param User $student
     * @param Response $response
     * @return void
     * @throws ClientExceptionInterface
     */
    public function getGenerateFeedback(DialogAgent $assessment, User $student, Response $response): void
    {
        // TODO: This should be a POST and actually persist the feedback. The frontend never needs this return data
        // TODO: Move this where it's most relevant

        $scenario = $this->scenarioMapper->findById($assessment->getScenarioId());

        $aiConfig = new \Schoolbox\Admin\OpenAI\Config();
        $agentConfig = $aiConfig->decode((string)$this->getConfigLoader()->get(ConfigMapper::NAME_AI_DIALOGAGENT));

        $agent = new OpenAI\Agent\DialogAgentFeedback(
            $this->aiClientFactory->buildForFeature(\Schoolbox\Editor\OpenAI\Helper::FEATURE_DIALOGAGENT),
            $agentConfig,
            $this->aiLogMapper,
        );

        /* Each feedback generation gets its own session ID */
        $aiSessionId = Uuid::uuid4()->toString();

        try {
            $feedbackData = $agent->request(
                $this->getUser(), /* user is optional, as this could be system-triggered */
                $aiSessionId,
                $scenario,
                $response,
            );
        } catch (SchoolboxException $e) {
            $problem = new ApiProblem($e->getMessage())
                ->setStatus($e->getCode())
                ->asArray();

            $this->response = $this
                ->responseAsJson($problem)
                ->withStatus($e->getCode());
            return;
        }

        // TODO: persist it

        $this->response = $this->responseAsJson($feedbackData);
    }
}
