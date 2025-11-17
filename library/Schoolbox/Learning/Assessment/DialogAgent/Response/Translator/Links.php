<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Response\Translator;

use Schoolbox\API\Translator\AbstractLinks;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Model;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Lookup\Status;

/**
 * Dialog Agent Response Links
 *
 * Set up the _links node with a bunch of permission-based urls that
 * $user can access
 */
class Links extends AbstractLinks
{
    /**
     * @param Model $response
     * @return array
     */
    public function __invoke(Model $response): array
    {
        $translation = [];

        $assessment = $response->getAssessable();

        $routeParamsResponse = [
            'assessment'   => $assessment,
            'assessmentId' => $assessment->getId(),
            'student'      => $response->getSubmittedOnBehalfOf(),
            'studentId'    => $response->getSubmittedOnBehalfOf()->getId(),
            'response'     => $response,
            'responseId'   => $response->getId(),
        ];

        $translation[Status::ACTION_VIEW] = $this->getRouteIfAuthorized('learningDialogAgent.getViewResponse', $routeParamsResponse);
        $translation[Status::ACTION_SOBO] = $this->getRouteIfAuthorized('learningDialogAgent.postSobo', $routeParamsResponse);
        $translation[Status::ACTION_REOPEN] = $this->getRouteIfAuthorized('learningDialogAgent.postReopen', $routeParamsResponse);
        $translation[Status::ACTION_RESOLVE] = $this->getRouteIfAuthorized('learningDialogAgent.postAssistanceResolve', $routeParamsResponse);

        return $translation;
    }
}
