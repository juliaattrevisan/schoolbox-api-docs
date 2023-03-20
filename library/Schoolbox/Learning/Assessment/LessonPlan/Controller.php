<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\LessonPlan;

use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;

class Controller extends Assessment\Controller
{
    /**
     * Construct an arbitrary array of data for twig rendering
     *
     * @param IAssessable $assessment
     * @return array
     */
    protected function getAssessmentTwigData(IAssessable $assessment): array
    {
        $zoneFactory = new Assessment\Attachzone\Factory();

        return [
            'acceptingResponses' => 'maybe',
            'attachzone' => $zoneFactory->buildForAssessment($assessment)->getTwigContext(),
        ];
    }


    /**
     * Endpoint for selecting a Lesson Plan Template
     *
     * @return void
     */
    public function getSelection()
    {
    }


    public function getStatus(IAssessable $assessment)
    {
        // TODO: Implement getStatus() method.
    }


    public function getSummary(IAssessable $assessment, User $student): void
    {
        // TODO: Implement getSummary() method.
    }


    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        // TODO: Implement getCreateResponseForm() method.
    }


    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        // TODO: Implement postCreateResponse() method.
    }
}
