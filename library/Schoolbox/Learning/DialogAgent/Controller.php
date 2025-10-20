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
use DebugBar\DebugBarException;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Mapper as DialogAgentMapper;
use Schoolbox\Admin\DialogAgent\Scenario\Mapper as ScenarioMapper;
use Schoolbox\Admin\DialogAgent\Scenario\Field\Context\Mapper as FieldContextMapper;
use Schoolbox\Admin\DialogAgent\Scenario\Field\Context\Model as FieldContextModel;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model as DialogAgentDataModel;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgent;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

class Controller extends Base
{
    /**
     * Constructor.
     *
     * @param BreadcrumbFactory $bakery
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param DialogAgentMapper $dialogAgentMapper
     * @param ScenarioMapper $scenarioMapper
     * @param FieldContextMapper $fieldContextMapper
     */
    public function __construct(
        private BreadcrumbFactory $bakery,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private DialogAgentMapper $dialogAgentMapper,
        private ScenarioMapper $scenarioMapper,
        private FieldContextMapper $fieldContextMapper,
    ) {
    }


    /**
     * Configure the dialog agent's settings
     *
     * @param DialogAgent $assessment
     * @return void
     * @throws \Twig\Error\Error
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
        $dialogAgent = $this->dialogAgentMapper->findOneByColumn('assessment_id', $assessment->getId());

        $fieldContexts = $this->fieldContextMapper->findByColumn('dialog_agent_id', $dialogAgent->getId())->indexByCallback(static function (FieldContextModel $instance) {
            return $instance->getScenarioField()->getId();
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
     * Update the configuration of a dialog agent
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

        /* Persist scenario fields
         *
         * Find prototypical instances
         */
        $existingFields = $this->scenarioMapper->findById($assessment->getScenarioId())->getFields()->indexBy('id');

        /* We need the dialog_agent.id for a schematic join. This follows a previous phenomenon where quiz
         * also had to be fetched separately to its assessment instance. A bit frustrating to need that,
         * but sub-object's fields don't really surface through the domain model for a reason.
         */
        $dialogAgentId = $this->dialogAgentMapper->findOneByColumn('assessment_id', $assessment->getId())->getId();

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
}
