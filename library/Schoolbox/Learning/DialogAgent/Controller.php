<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\DialogAgent;

use Alaress\Web\Controller\Base;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model as DialogAgentDataModel;
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgent;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct() {
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
        $scenarioMapper = \Schoolbox\Admin\DialogAgent\Scenario\Mapper::getInstance();

        $data = [
            'assessment' => $assessment,
            'scenario' => $scenarioMapper->findById($assessment->getScenarioId()),
            'helpfulness' => [
                DialogAgentDataModel::HELPFULNESS_MINIMAL => 'Minimal',
                DialogAgentDataModel::HELPFULNESS_MODERATE => 'Moderate',
                DialogAgentDataModel::HELPFULNESS_EXTREME => 'Extreme',
            ],
            'language' => [
                'en-gb' => 'English (AU)',
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
     */
    public function postUpdate(DialogAgent $assessment): void
    {

    }
}
