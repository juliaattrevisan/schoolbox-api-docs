<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\DialogAgent;

use Alaress\Web\Controller\Base;
use DebugBar\DebugBarException;
use Schoolbox\Admin\DialogAgent\Scenario\Mapper;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model as DialogAgentDataModel;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgent;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

class Controller extends Base
{
    /**
     * Constructor.
     *
     * @param BreadcrumbFactory $bakery
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param Mapper $scenarioMapper
     */
    public function __construct(
        private BreadcrumbFactory $bakery,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private \Schoolbox\Admin\DialogAgent\Scenario\Mapper $scenarioMapper
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

        $data = [
            'assessment' => $assessment,
            'breadcrumbs' => $breadcrumbs,
            'scenario' => $this->scenarioMapper->findById($assessment->getScenarioId()),
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
     * @throws DebugBarException
     * @throws Exception
     * @throws Deleted
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

        new \Alaress_Probe($postArray);
    }
}
