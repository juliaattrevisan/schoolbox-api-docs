<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti;

use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;

class Controller extends Assessment\Controller
{
    protected function getAssessmentTwigData(IAssessable $assessment): array
    {
        return [];
    }

    public function getStatus(IAssessable $assessment)
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/ltiAssessment/status.twig',
                [],
                "Status of {$assessment->getTitle()}",
                'submission'
            )
        );
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
