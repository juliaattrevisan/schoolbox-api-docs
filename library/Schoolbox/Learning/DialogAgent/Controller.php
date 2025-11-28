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
use Schoolbox\Admin\DialogAgent\Scenario\Field\Context\Model as FieldContextModel;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model as DialogAgentDataModel;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DialogAgent\Lookup\Language;
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgent;
use Schoolbox\Learning\Assessment\DialogAgent\Response\MapperProxy as DialogAgentResponseMapper;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Model as Response;
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
     * @param DialogAgentDataModelMapper $dialogAgentDataModelMapper
     * @param DialogAgentResponseMapper $dialogAgentResponseMapper
     * @param ScenarioMapper $scenarioMapper
     * @param FieldContextMapper $fieldContextMapper
     */
    public function __construct(
        private BreadcrumbFactory $bakery,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private OpenAI\ClientFactory $aiClientFactory,
        private DialogAgentDataModelMapper $dialogAgentDataModelMapper,
        private DialogAgentResponseMapper $dialogAgentResponseMapper,
        private ScenarioMapper $scenarioMapper,
        private FieldContextMapper $fieldContextMapper,
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

        $fieldContexts = $this->fieldContextMapper->findByColumn('dialog_agent_id', $dialogAgent->getId())->indexByCallback(static function (FieldContextModel $instance) {
            return $instance->getField()->getId();
        });

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
                    'id' => $fieldContext->getField()->getId(),
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
         * TODO: split this out into something reusable I guess
         */
        $fieldReplacements = [];
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
     * @param IAssessable $assessment
     * @param User $student
     * @return void
     */
    public function getViewResponse(IAssessable $assessment, User $student): void
    {
        echo "View a response";
        // todo implement a readonly viewing mode
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
        $agentConfig = $aiConfig->decode((string)$this->getConfigLoader()->get(ConfigMapper::NAME_AI_TEXT_CHAT));

        /**
         * For dialog agent response chats, the sessionID guid is directly tied to the response,
         * If the student has never sent a message before, a new session ID should be created
         */
        $aiSessionId = $response->getSessionId();
        if (null === $aiSessionId) {
            $aiSessionId = Uuid::uuid4()->toString();
            $response->setSessionId($aiSessionId);
            // TODO: you can't actually save here as the log doesn't exist yet
            // $this->dialogAgentResponseMapper->save($response);
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
        $agentConfig = $aiConfig->decode((string)$this->getConfigLoader()->get(ConfigMapper::NAME_AI_TEXT_CHAT));

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

        $this->translatePayload($response);
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

        $this->translatePayload($response);
    }


    /**
     * Forcibly submit a student's authoritative response
     *
     * @param IAssessable $assessment
     * @param User $student
     * @param Response $response
     * @return void
     */
    public function postSobo(IAssessable $assessment, User $student, Response $response): void
    {
        $response
            ->setSubmittedAt(\Alaress_Date_Format::now())
            ->setSubmittedOnBehalfOf($student)
            ->setSubmittedBy($this->getUser());

        $this->dialogAgentResponseMapper->save($response);

        $this->translatePayload($response);
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

        $this->translatePayload($response);
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

        $this->translatePayload($newResponse);
    }


    /**
     * Returns a json payload conversion of $response
     *
     * @param Response $response
     * @return void
     */
    private function translatePayload(Response $response): void
    {
        $responseLinksTranslator = new Translator\Links($this->routeGenerator, $this->getAuthorizer(), $this->getUser());
        $responseTranslator = new Translator\ModelToArray($responseLinksTranslator);

        $this->response = $this->responseAsJson([
            'response' => $responseTranslator->__invoke($response),
        ])->withStatus(200);
    }
}
