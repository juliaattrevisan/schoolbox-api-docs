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
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriInterface;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Ramsey\Uuid\Uuid;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Mapper as DialogAgentDataModelMapper;
use Schoolbox\Admin\DialogAgent\Scenario\Mapper as ScenarioMapper;
use Schoolbox\Admin\DialogAgent\Scenario\Field\Context\Model as ScenarioFieldContext;
use Schoolbox\Admin\DialogAgent\Scenario\Field\Context\Mapper as FieldContextMapper;
use Schoolbox\Admin\DialogAgent\Scenario\Criterion\Mapper as CriterionMapper;
use Schoolbox\Admin\DifferentiationProfiles\Mapper as DifferentiationProfileAdminMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\DifferentiationProfile\Mapper as DifferentiationProfileMapper;
use Schoolbox\DifferentiationProfile\Translator\ProfileToArray as DifferentiationProfileTranslator;
use Schoolbox\Editor\OpenAI\Log\Mapper as AILogMapper;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message\Mapper as DialogAgentResponseMessageMapper;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message\Translator\ModelToArray as DialogAgentResponseMessageTranslator;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DialogAgent\Lookup\Helpfulness;
use Schoolbox\Learning\Assessment\DialogAgent\Lookup\Language;
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgent;
use Schoolbox\Learning\Assessment\DialogAgent\Response\MapperProxy as DialogAgentResponseMapper;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Model as Response;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Status;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Translator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Permission\Access\Access;
use Schoolbox\OpenAi;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\HttpRequest\MessageFactory;
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
     * @param Access $access
     * @param BreadcrumbFactory $bakery
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param UriInterface $baseUrl This Schoolbox instance's base URL: required for sending a polling task to the task daemon.
     * @param RequestFactoryInterface $httpFactory A HTTP request factory: required for creating requests to send to the task daemon.
     * @param MessageFactory $messageFactory A message factory: used to create the message to send to the task daemon.
     * @param Producer $messageProducer Used to send messages to the task daemon once built.
     * @param Participant\DataSource $participantDataSource
     * @param OpenAI\ClientFactory $aiClientFactory
     * @param AILogMapper $aiLogMapper
     * @param DialogAgentDataModelMapper $dialogAgentDataModelMapper
     * @param \Schoolbox\Admin\DialogAgent\Scenario\Criterion\Mapper $scenarioCriterionMapper
     * @param \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Mapper $feedbackDataModelMapper
     * @param \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Criterion\Mapper $feedbackCriterionMapper
     * @param DialogAgentResponseMapper $dialogAgentResponseMapper
     * @param ScenarioMapper $scenarioMapper
     * @param FieldContextMapper $fieldContextMapper
     * @param CriterionMapper $criterionMapper,
     * @param \Schoolbox\Learning\Assessment\DialogAgent\Feedback\MapperProxy $feedbackDataSource
     * @param DialogAgentResponseMessageMapper $dialogAgentResponseMessageMapper
     * @param DifferentiationProfileMapper $differentiationProfileMapper
     * @param DifferentiationProfileAdminMapper $differentiationProfileAdminMapper
     */
    public function __construct(
        private Access $access,
        private BreadcrumbFactory $bakery,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private UriInterface $baseUrl,
        private RequestFactoryInterface $httpFactory,
        private MessageFactory $messageFactory,
        private Producer $messageProducer,
        private Participant\DataSource $participantDataSource,
        private OpenAi\ClientFactory $aiClientFactory,
        private AILogMapper $aiLogMapper,
        private DialogAgentDataModelMapper $dialogAgentDataModelMapper,
        private \Schoolbox\Admin\DialogAgent\Scenario\Criterion\Mapper $scenarioCriterionMapper,
        private \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Mapper $feedbackDataModelMapper,
        private \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Criterion\Mapper $feedbackCriterionMapper,
        private DialogAgentResponseMapper $dialogAgentResponseMapper,
        private ScenarioMapper $scenarioMapper,
        private FieldContextMapper $fieldContextMapper,
        private CriterionMapper $criterionMapper,
        private \Schoolbox\Learning\Assessment\DialogAgent\Feedback\MapperProxy $feedbackDataSource,
        private DialogAgentResponseMessageMapper $dialogAgentResponseMessageMapper,
        private DifferentiationProfileMapper $differentiationProfileMapper,
        private DifferentiationProfileAdminMapper $differentiationProfileAdminMapper,
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
        $dialogAgentId = $this->dialogAgentDataModelMapper->findOneByColumn('assessment_id', $assessment->getId())->getId();

        $fieldContexts = $this->fieldContextMapper->findByColumn('dialog_agent_id', $dialogAgentId);

        $scenario = $this->scenarioMapper->findById($assessment->getScenarioId());

        $data = [
            'assessment' => $assessment,
            'breadcrumbs' => $breadcrumbs,
            'scenario' => [
                'title' => $scenario->getTitle(),
                'description' => $scenario->getDescription(),
            ],
            'assessmentConfig' => [
                'helpfulness' => $assessment->getHelpfulness() ?? Helpfulness::DEFAULT,
                'language' => $assessment->getLanguage() ?? Language::DEFAULT,
                'openingMessage' => \Scrub::markdown($assessment->getOpeningMessage()),
                'rules' => \Scrub::markdown($assessment->getRules() ?: $scenario->getDefaultRules()),
                'useDifferentiationProfile' => $assessment->getUseDifferentiationProfile(),
                'fieldContexts' => $fieldContexts->map(fn (ScenarioFieldContext $fieldContext) => [
                    'id' => $fieldContext->getId(),
                    'body' => \Scrub::markdown($fieldContext->getBody() ?? ''),
                    'field' => [
                        'id' => $fieldContext->getField()->getId(),
                        'title' => $fieldContext->getField()->getTitle(),
                        'meta' => $fieldContext->getField()->getMeta(),
                    ],
                ])->reindex(),
                'prompt' => $assessment->getPrompt(),
                '_routes' => [
                    'postSandboxMessage' => $this->getRouteIfAuthorized('learningDialogAgent.postSandboxMessage', [
                        'assessmentId' => $assessment->getId(),
                        'assessment' => $assessment,
                    ]),
                ],
            ],
            // derive from the Helpfulness enum
            'helpfulnessOptions' => array_map(fn (Helpfulness $case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ], Helpfulness::cases()),
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

        $requiredTextFields = ['openingMessage', 'rules'];
        foreach ($requiredTextFields as $field) {
            if (empty(trim((string)$postArray[$field]))) {
                $problem = new ApiProblem(\sprintf('The "%s" field is required.', $field))
                    ->setStatus(400)
                    ->asArray();

                $this->response = $this->responseAsJson($problem)
                    ->withStatus(400);

                return;
            }
        }

        $assessment->setHelpfulness(Helpfulness::from($postArray['helpfulness']))
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
            $body = trim((string)($postArray['fieldContexts'][$fieldContextId] ?? ''));
            if (empty($body)) {
                $problem = new ApiProblem(\sprintf('The "%s" field is required.', $fieldContext->getField()->getTitle()))
                    ->setStatus(400)
                    ->asArray();

                $this->response = $this->responseAsJson($problem)
                    ->withStatus(400);

                return;
            }

            $existingFieldContexts[$fieldContextId]->setBody($body);
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
            '<helpfulness>' => $assessment->getHelpfulness()->prompt(),
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
            'prompt' => $assessment->getPrompt(),
            'openingMessage' => \Scrub::markdown($assessment->getOpeningMessage()),
            '_routes' => [
                'postSandboxMessage' => $this->getRouteIfAuthorized('learningDialogAgent.postSandboxMessage', [
                    'assessmentId' => $assessment->getId(),
                    'assessment' => $assessment,
                ]),
            ],
        ]);
    }


    /**
     * Request AI for an initial field configuration for a dialog agent activity to be generated
     *
     * @param DialogAgent $assessment
     * @return void
     * @throws ClientExceptionInterface
     * @throws Deleted
     * @throws Exception
     * @throws \JsonException
     */
    public function getGenerateConfigurationFields(DialogAgent $assessment): void
    {
        $scenario = $this->scenarioMapper->findById($assessment->getScenarioId());

        $aiConfig = new \Schoolbox\Admin\OpenAI\Config();
        $agentConfig = $aiConfig->decode((string)$this->getConfigLoader()->get(ConfigMapper::NAME_AI_DIALOGAGENT));

        $agent = new OpenAI\Agent\DialogAgentConfigureFields(
            $this->aiClientFactory->buildForFeature(\Schoolbox\Editor\OpenAI\Helper::FEATURE_DIALOGAGENT),
            $agentConfig,
        );

        /* Each feedback generation gets its own session ID */
        $aiSessionId = Uuid::uuid4()->toString();

        try {
            $fieldsData = $agent->request(
                $this->getUser(),
                $aiSessionId,
                $scenario,
                $assessment,
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

        $this->response = $this->responseAsJson($fieldsData);
    }


    /**
     * View a response's chat log in a readonly mode
     *
     * @param DialogAgent $assessment
     * @param User $student
     * @param ?Response $response - If null, will find the authoritative response for the participant if any
     * @return void
     * @throws Error
     * @throws Exception
     * @throws RouteNotFound
     */
    public function getParticularResponse(DialogAgent $assessment, User $student, ?Response $response): void
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

        if (!$response) {
            /** @var ?Response $response */
            $response = $participant->getAuthoritativeResponse(false);
        }

        /**
         * If you can access the submit form, and dialog agent is enabled, the learner has
         * likely ended up on this page by mistake (instructor sent a link, or learner refreshed an outdated page)
         *
         * If $response isn't the current authoritative response, this submit route will take you to the wrong place.
         *
         * Redirect them to the create form instead as long as dialog agent is enabled (due to the extra check at the start of getCreateResponseForm)
         */
        $createFormRoute = $response?->getAuthoritative() ? $this->getRouteIfAuthorized('learningAssessment.getCreateResponseForm', [
            'assessment' => $assessment,
            'assessmentId' => $assessment->getId(),
            'student' => $student,
            'studentId' => $student->getId(),
        ]) : null;
        if ($createFormRoute && new ValidDialogAgentEnabled($this->getConfigLoader())->isSatisfied()) {
            $this->response = $this->redirectTemporary($createFormRoute);
            return;
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
                'message' => \Scrub::markdown($assessment->getOpeningMessage()),
            ],
            'createdAt' => $assessment->getCreatedAt(), // this message should always appear first
        ]);

        if ($response) {
            /* Create an assistance request feed message if the user can see it */
            $isViewerAnInstructor = $this->access->isInstructor($this->getUser(), $assessment->getBaseContext()->getFolder());
            if ($response->getAssistanceRequestedAt() !== null && $isViewerAnInstructor) {
                $feed->add([
                    'type' => 'alert',
                    'data' => [
                        'side' => 'request',
                        'severity' => 'warning-tint',
                        'message' => \sprintf(
                            '%s requested assistance %s',
                            $student->getFullName(),
                            $response->getAssistanceRequestedAt()->getRelativeTime(),
                        ),
                    ],
                    'createdAt' => $response->getAssistanceRequestedAt(),
                ]);
            }

            // TODO: Inject the guardrails warning in the middle of that where appropriate and if the user has access to see it

            $responseMessageTranslator = new DialogAgentResponseMessageTranslator();
            $responseMessages = $this->dialogAgentResponseMessageMapper->findForResponse($response->getId());
            foreach ($responseMessages as $message) {
                $feed->add($responseMessageTranslator->__invoke($message));
            }
        }

        $feed->sortByCallback(fn ($a, $b) => $a['createdAt'] <=> $b['createdAt']);

        $userTranslator = new User\Translator\Author\ModelToArray();
        $responseLinksTranslator = new Translator\Links($this->routeGenerator, $this->getAuthorizer(), $this->getUser());
        $responseTranslator = new Translator\ModelToArray($responseLinksTranslator);

        /**
         * Generate the participant carousel, only if you have access to the status page
         */
        $statusRoute = $this->getRouteIfAuthorized('learningAssessment.getStatus', [
            'assessment' => $assessment,
            'assessmentId' => $assessment->getId(),
        ]);

        $participantCarousel = [];
        if ($statusRoute) {
            /** since this route is loaded for a specific participant, only that participant is hydrated by the module. we need the rest */
            $assessment = $this->participantDataSource->loadParticipationIntoAssessment($assessment);
            foreach ($assessment->getAllParticipants() as $participant) {
                /* Are they excluded? ignore them */
                if ($participant->isExcluded()) {
                    continue;
                }

                $authoritativeResponse = null;
                if ($participant->getAllResponses()->count()) {
                    /** @var Response|null $authoritativeResponse */
                    $authoritativeResponse = $assessment->getAuthoritativeResponse($participant, true);
                }

                $status = $authoritativeResponse?->getCurrentStatus() ?? Status::INCOMPLETE;

                $participantCarousel[] = [
                    'id' => $participant->getUser()->getId(),
                    'value' => $this->getRouteIfAuthorized('learningDialogAgent.getParticularResponse', [
                        'assessment' => $assessment,
                        'assessmentId' => $assessment->getId(),
                        'student' => $participant->getUser(),
                        'studentId' => $participant->getUser()->getId(),
                    ]),
                    'user' => $userTranslator->__invoke($participant->getUser()),
                    'status' => $status,
                    'statusLabel' => $status->translate(),
                ];
            }
        }

        /* If you can get to the status page, use that as it's more relevant.
         * otherwise since students can access this route, fall back to the summary page
         */
        $assessmentCrumbRoute = $this->getRouteIfAuthorized('learningAssessment.getStatus', [
            'assessment' => $assessment,
            'assessmentId' => $assessment->getId(),
        ]);
        if (!$assessmentCrumbRoute) {
            $assessmentCrumbRoute = $this->getRouteIfAuthorized('learningAssessment.getSummary', [
                'assessment' => $assessment,
                'assessmentId' => $assessment->getId(),
                'student' => $student,
                'studentId' => $student->getId(),
            ]);
        }

        $data = [
            'breadcrumbs' => $this->bakery->produceFromAssessment($assessment, $this->getUser())->add([
                $assessment->getTitle(),
                $assessmentCrumbRoute,
            ])->add(['Response']),
            'assessment' => $assessment,
            'feed' => $feed,
            'student' => $student,
            'studentData' => $userTranslator->__invoke($student),
            'response' => $response,
            'responseData' => $response ? $responseTranslator->__invoke($response) : null,
            'interactionLocked' => false, // TODO
            'returnUrl' => $returnUrl,
            'participantCarousel' => $participantCarousel,
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
     * Redirect user to the view page for the first participant in the assessment list
     *
     * @param DialogAgent $assessment
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getFirstParticipantResponse(DialogAgent $assessment)
    {
        $participants = $assessment->getAllParticipants()->filter(fn($participant) => !$participant->isExcluded());
        $firstParticipant = $participants->first()?->getUser();

        if ($firstParticipant instanceof User) {
            $this->response = $this->redirectFound(
                $this->routeGenerator->generate('learningDialogAgent.getParticularResponse', [
                    'assessment' => $assessment,
                    'assessmentId' => $assessment->getId(),
                    'student' => $firstParticipant,
                    'studentId' => $firstParticipant->getId(),
                ]),
            );
            return;
        }

        /* If the participant list is empty, nothing can be shown */
        throw $this->generateException(\Schoolbox\Error\Mapper::ERROR_PAGE_NOT_FOUND);
    }


    /**
     * Send a message to the chat agent for a particular response and stream the reply
     *
     * @param DialogAgent $assessment
     * @param Response $response
     * @return void
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function postMessage(DialogAgent $assessment, Response $response): void
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

        /**
         * If differentiation profiles are enabled, and the assessment is set to use them,
         * Try to find the differentiation profile for the learner and add it to the prompt if found
         *
         * Note that the AI feature of differentiation profiles is optional and isn't what we're doing here,
         * as such the AI settings of diff profiles are not checked. Differentiation profiles allow for users
         * to fully create the profiles themselves so long as the feature is enabled.
         */
        $differentiationProfilePrompt = null;
        if (
            $assessment->getUseDifferentiationProfile() &&
            $this->getConfigLoader()->get(ConfigMapper::NAME_DIFFERENTIATION_PROFILE_ENABLED)
        ) {
            $learner = $response->getSubmittedOnBehalfOf();
            $differentiationProfile = null;
            try {
                $differentiationProfile = $this->differentiationProfileMapper->findOneByColumn(
                    'student_id',
                    $learner->getId(),
                    [
                        'order' => ['updated_at' => \Schoolbox\Mapper::SORT_ORDER_DESC],
                    ]
                );
            } catch (\Schoolbox\Mapper\Exception\NotFound) {
                // noop
            }

            if (null !== $differentiationProfile) {
                $differentiationProfileTranslator = new DifferentiationProfileTranslator(
                    $learner,
                    $this->differentiationProfileAdminMapper->findByColumn('is_enabled', 1),
                );

                $differentiationProfilePrompt = json_encode(
                    $differentiationProfileTranslator($differentiationProfile, includeEmptyFields: false),
                    JSON_THROW_ON_ERROR
                );
            }
        }

        // only provide the moderation client if enabled for this feature
        $moderationClient = $aiConfig->isAiModerationEnabled($agentConfig)
            ? $this->aiClientFactory->buildOpenAiModeration()
            : null;

        $agent = new OpenAI\Agent\DialogAgentChat(
            $this->aiClientFactory->buildForFeature(\Schoolbox\Editor\OpenAI\Helper::FEATURE_DIALOGAGENT),
            $moderationClient,
            $agentConfig,
            $this->dialogAgentResponseMapper,
            $this->dialogAgentResponseMessageMapper,
        );

        try {
            $response
                ->setUpdatedBy($this->getUser()->getId())
                ->setUpdatedAt(\Alaress_Date_Format::now());

            $stream = $agent->requestStream(
                $this->getUser(),
                $assessment,
                $this->requestPost->get('userPrompt'),
                $differentiationProfilePrompt,
                $aiSessionId,
                previousResponseId: $this->requestPost->get('previousResponseId') ?? null,
                responseModel: $response,
            );

            /**
             * We must wait until now to save, as the log must exist
             * for the session ID foreign key constraint to be satisfied
             */
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
                ->setDetail($e->getInformation())
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
     * @throws ClientExceptionInterface
     * @throws \JsonException
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

        // only provide the moderation client if enabled for this feature
        $moderationClient = $aiConfig->isAiModerationEnabled($agentConfig)
            ? $this->aiClientFactory->buildOpenAiModeration()
            : null;

        $agent = new OpenAI\Agent\DialogAgentChat(
            $this->aiClientFactory->buildForFeature(\Schoolbox\Editor\OpenAI\Helper::FEATURE_DIALOGAGENT),
            $moderationClient,
            $agentConfig,
            $this->dialogAgentResponseMapper,
            $this->dialogAgentResponseMessageMapper,
        );

        try {
            $stream = $agent->requestStream(
                $this->getUser(),
                $assessment,
                $this->requestPost->get('userPrompt'),
                null, // no differentiation profile in the sandbox
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
                ->setDetail($e->getInformation())
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
     * @param Response $response
     * @return void
     */
    public function postAssistanceRequest(Response $response): void
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
     * @param Response $response
     * @return void
     */
    public function postAssistanceResolve(Response $response): void
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
     * @throws RouteNotFound
     * @throws \Interop\Queue\Exception
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     */
    public function postSubmit(IAssessable $assessment, User $student, Response $response): void
    {
        $response
            ->setSubmittedAt(\Alaress_Date_Format::now())
            ->setSubmittedOnBehalfOf($student)
            ->setSubmittedBy($this->getUser());

        /**
         * If the instructor submitted on behalf of the student, it's likely that the response
         * should no longer count as "locked" if it were. Otherwise the response would remain "locked" unless reopened
         *
         * An instructor submitting a locked response indicates they have reviewed the reason for the lock
         * and concluded that the student shouldn't continue
         */
        if ($this->access->isInstructor($this->getUser(), $assessment->getBaseContext()->getFolder())) {
            $response->setLockedAt(null);
        }

        $this->dialogAgentResponseMapper->save($response);

        /** Set up and send off a task daemon job to generate the feedback for this response */
        $route = $this->routeGenerator->generate(
            'learningDialogAgent.postGenerateFeedback',
            [
                'assessmentId' => $assessment->getId(),
                'studentId' => $student->getId(),
                'responseId' => $response->getId(),
            ],
        );

        $request = $this->httpFactory
            ->createRequest('POST', $this->baseUrl->withPath($route))
            ->withHeader('accept', 'application/json');

        $message = $this->messageFactory
            ->setUser($this->getUser())
            ->setRequest($request)
            ->build();

        $this->messageProducer->send($message, maxRequeues: 10);

        /** Respond as JSON with a consistent translated response data */
        $this->getStatus($response);
    }


    /**
     * Reopening a dialog agent for a particular student basically means "unsubmit this student's authoritative response"
     * The responseId provided must be the student's authoritative response for it to be reopened.
     *
     * @param Response $response
     * @return void
     */
    public function postReopen(Response $response): void
    {
        $response
            ->setLockedAt(null) // in case it was locked, this is how you unlock it
            ->setSubmittedAt(null)
            ->setSubmittedBy(null);

        $this->dialogAgentResponseMapper->save($response);

        /* Soft-delete any feedbacks and feedback criteria that might already have existed; this caters for the Reopen
         * scenario which would have already spawned a feedback.
         */
        $this->feedbackDataModelMapper->deleteByColumn('response_id', $response->getId());

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
     * @return void
     * @throws Deleted
     * @throws Exception
     */
    public function postReset(IAssessable $assessment, User $student): void
    {
        /* Make sure all existing responses for this participant are set to non-authoritative
         */
        $participant = $assessment->getParticipant($student);
        foreach ($participant->getAllResponses() as $existingResponse) {
            /** @var Response $existingResponse */
            if ($existingResponse->getAuthoritative()) {
                $existingResponse->setAuthoritative(false);
                $this->dialogAgentResponseMapper->save($existingResponse);
            }
        }

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
     * Change a particular response to be the authoritative one for the participant
     *
     * @param IAssessable $assessment
     * @param User $student
     * @param Response $response
     * @return void
     */
    public function postSetAuthoritative(IAssessable $assessment, User $student, Response $response): void
    {
        /* Make sure all existing responses for this participant are set to non-authoritative
         */
        $participant = $assessment->getParticipant($student);
        foreach ($participant->getAllResponses() as $existingResponse) {
            /** @var Response $existingResponse */
            if ($existingResponse->getAuthoritative()) {
                $existingResponse->setAuthoritative(false);
                $this->dialogAgentResponseMapper->save($existingResponse);
            }
        }

        /* Set this one to authoritative
         */
        $response->setAuthoritative(true);
        $this->dialogAgentResponseMapper->save($response);

        /** Respond as JSON with a consistent translated response data */
        $this->getStatus($response);
    }


    /**
     * Provides a json payload conversion of $response including routes/status
     * Used for asynchronous calls when the response state may have changed
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
        $criteria = $this->criterionMapper->findByColumn('scenario_id', $assessment->getScenarioId());

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

                $currentStatus = $response?->getCurrentStatus() ?? Status::INCOMPLETE;

                if (!($requestedStatuses[$currentStatus->value] ?? false)) {
                    continue;
                }

                /* Make the same number of elements as $fields for now; these will eventually have feedback criteria in them
                 * but they should correspond 1:1 with $fields that we defined above
                 */
                $feedbackFields = array_fill_keys($feedbackFieldsTemplate, null);

                /* Compute outcomes for each criterion
                 */
                if ($authoritativeFeedback) {
                    /** @var \Schoolbox\Learning\Assessment\DialogAgent\Feedback\Criterion\Model $feedbackCriterion */
                    foreach ($authoritativeFeedback->getCriteria() as $feedbackCriterion) {
                        $feedbackFields[$feedbackCriterion->getScenarioCriterionId()] = $feedbackCriterion->getBody();
                    }
                }

                /* Slam them all together if they're part of the requested set of states
                 */
                fputcsv($fp, array_merge([
                    $participant->getUser()->getId(),
                    $participant->getUser()->getExternalId(),
                    $participant->getUser()->getFirstName(),
                    $participant->getUser()->getLastName(),
                    $currentStatus->translate(),
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
     * taskd postback webhook
     *
     * Generate feedback for a particular dialog agent response
     * This route is called by the task daemon after a response is submitted by a user or by the cron
     *
     * @param DialogAgent $assessment
     * @param Response $response
     * @return void
     * @throws ClientExceptionInterface
     * @throws Deleted
     * @throws Exception
     * @throws \JsonException
     */
    public function postGenerateFeedback(DialogAgent $assessment, Response $response): void
    {
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

        /* Spawn a new feedback row
         */
        $now = \Alaress_Date_Format::now();

        $feedback = $this->feedbackDataSource->createModel()
            ->setAssessable($assessment)
            ->setResponse($response)
            ->setReviewee($response->getSubmittedOnBehalfOf())
            ->setReviewer(null)
            ->setCriteria(new \Schoolbox\GenericCollection(\Schoolbox\Learning\Assessment\DialogAgent\Feedback\Criterion\Model::class))
            ->setPublishedAt($now)
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
            ->setDeletedAt(null);

        $this->feedbackDataSource->save($feedback);

        /* Grab its id and then persist each cell's data
         */
        foreach ($feedbackData as $scenarioCriterionId => $value) {
            $feedbackCriterion = $this->feedbackCriterionMapper->createModel()
                /* This strange awkwardness about Scenario Criterion and Feedback is something of a swampy byproduct where
                 * you can't tell how much hydration you actually needed vs expected; plus, we never really hydrated stuff
                 * to this extent prior to now (closest is Rubric Grids which are dealt with totally separately)
                 * You could possibly just replace scenario criterion instances with just an id, but that is a bit weird in
                 * and of itself these days.
                 */
                ->setFeedback($this->feedbackDataModelMapper->createModel()->setId($feedback->getId()))
                ->setScenarioCriterion($this->scenarioCriterionMapper->createModel()->setId($scenarioCriterionId))
                /* Set the ai's text and various meta here forcibly coercing $value to something nice
                 */
                ->setBody(trim((string)$value))
                ->setCreatedAt($now)
                ->setUpdatedAt($now)
                ->setDeletedAt(null);

                $this->feedbackCriterionMapper->save($feedbackCriterion);
        }

        $this->response = $this->responseEmpty(200);
    }
}
