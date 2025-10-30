<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\DialogAgent;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Assessment\Term\Mapper;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Mapper as DialogAgentDataModelMapper;
use Schoolbox\Admin\DialogAgent\Scenario\Mapper as ScenarioMapper;
use Schoolbox\Admin\DialogAgent\Scenario\Field\Context\Mapper as FieldContextMapper;
use Schoolbox\Admin\DialogAgent\Scenario\Field\Context\Model as FieldContextModel;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model as DialogAgentDataModel;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgent;
use Schoolbox\Learning\Assessment\DialogAgent\Response\MapperProxy as DialogAgentResponseMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LockedChecker;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
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
     * @param Mapper $termMapper
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param DialogAgentDataModelMapper $dialogAgentDataModelMapper
     * @param DialogAgentResponseMapper $dialogAgentResponseMapper
     * @param ScenarioMapper $scenarioMapper
     * @param FieldContextMapper $fieldContextMapper
     */
    public function __construct(
        private BreadcrumbFactory $bakery,
        private \Schoolbox\Assessment\Term\Mapper $termMapper,
        private AssessmentMapperProxy $assessmentMapperProxy,
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

        $data = [
            'assessment' => $assessment,
            'breadcrumbs' => $breadcrumbs,
            'scenario' => $this->scenarioMapper->findById($assessment->getScenarioId()),
            'fieldContexts' => $fieldContexts,
            'helpfulness' => [
                DialogAgentDataModel::HELPFULNESS_MINIMAL => 'Minimal',
                DialogAgentDataModel::HELPFULNESS_MODERATE => 'Moderate',
                DialogAgentDataModel::HELPFULNESS_EXTREME => 'Extreme',
            ],
            'language' => [
                'en-au' => 'English (AU)',
                'en-us' => 'English (US)',
                /* TODO what's the full list of languages here...?
                 */
            ]
        ];

        $this->response = $this->responseFromTemplate('@learningDialogAgent/update.twig', $data, sprintf('Configure %s', $assessment->getTitle()));
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

        /* TODO Reset the chat here
         */

        /* Respond to synchronous POST with redirect
         */
        $returnUrlEncoded = $this->requestPost['returnUrl'] ?? '';

        if ($returnUrlEncoded !== '') {
            $returnUrl = Redirect::decode($returnUrlEncoded);
        } else {
            /* If no return URL supplied, redirect to configuration page, under the assumption that you'll
             * probably want to try out your changes to the dialog agent configuration...
             */
            $returnUrl = $this->getRouteIfAuthorized('learningDialogAgent.getUpdateForm', [
                'assessmentId' => $assessment->getId(),
                'assessment' => $assessment,
            ]);
        }

        $this->response = $this->redirectAfterPost($returnUrl);
    }


    /**
     * Resume an unsubmitted response
     *
     * @param IAssessable $assessment
     * @param User $student
     * @return void
     * @throws Deleted
     * @throws Error
     * @throws Exception
     * @throws RouteNotFound
     */
    public function getResumeResponse(IAssessable $assessment, User $student): void
    {
        /** @var null|Participant $participant */
        $participant = $assessment->getParticipantIfExtant($student);

        /* Find out if there's an unsubmitted dialog agent linked to a session (Resume) and create a
         * button which goes to that particular response so students can continue it.
         *
         * Otherwise create a new response.
         */
        $existingResponse = null;

        if ($participant) {
            $pb = $this->dialogAgentResponseMapper->getPredicateBuilder()
                ->equals('dialog_agent_id', $this->dialogAgentDataModelMapper->findOneByColumn('assessment_id', $assessment->getId())->getId())
                ->equals('learner_id', $participant->getUser()->getId())
                ->isNull('submitted_by')
                ->isNull('submitted_at');

            $existingResponse = $this->dialogAgentResponseMapper->findByPredicates($pb)->first();
        }

        // Is the page being displayed in readonly mode?
        /* TODO Are students allowed to visit this after the activity is closed?
         */
        $isInteractionLocked = (new LockedChecker($this->termMapper))->isLockedForInteractions($assessment);

        // For some reason I can't use get/setFlash?
        $errorMessage = $this->getSession()->getSegment(static::class)->get('errorMessage');
        $this->getSession()->getSegment(static::class)->set('errorMessage', null);

        /* Prepare a redirection url
         */
        if ($this->requestQuery->get(Redirect::RETURN_URL)) {
            $returnUrl = Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL));
        } else {
            $returnUrl = '/homepage/' . $assessment->getBaseContext()->getFolder()->getId();
        }
        $data = [
            'breadcrumbs' => $this->bakery->produceFromAssessment($assessment, $this->getUser())->add(
                [
                    sprintf('Create response for %s', $assessment->getTitle()),
                ]
            ),
            'assessment' => $assessment,
            'student' => $student,
            'participant' => $participant,
            'existingResponse' => $existingResponse,
            'errorMessage' => $errorMessage,
            'readonly' => $isInteractionLocked,
            'returnUrl' => $returnUrl,
        ];

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/dialogAgent/response/create.twig',
            $data
        );
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
        // todo implement a readonly viewing mode
    }
}
