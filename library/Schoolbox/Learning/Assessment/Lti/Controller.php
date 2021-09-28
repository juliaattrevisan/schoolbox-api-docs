<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti;

use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;

class Controller extends Assessment\Controller
{
    /**
     * Fill in Twig data required for rendering the create LTI assessment form.
     *
     * @param IAssessable $assessment
     * @return array
     */
    protected function getAssessmentTwigData(IAssessable $assessment): array
    {
        return [
            'acceptingResponses' => true,
        ];
    }

    public function getStatus(IAssessable $assessment)
    {
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/lti/status.twig',
                [
                    'assessment' => $assessment,
                ],
                "Status of {$assessment->getTitle()}",
                'submission'
            )
        );
    }

    public function getSummary(IAssessable $assessment, User $student): void
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/lti/summary.twig',
                [
                    'assessment' => $assessment,
                    'student'    => $student,
                ],
                "Status of {$assessment->getTitle()}",
                'submission'
            )
        );
    }

    /**
     * @param IAssessable $assessment
     * @param User        $student
     */
    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        throw new NotFound();
    }

    /**
     * @param IAssessable $assessment
     * @param User        $student
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        throw new NotFound();
    }
}
