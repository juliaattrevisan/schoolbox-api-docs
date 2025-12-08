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

        $translation['status'] = $this->getRouteIfAuthorized('learningDialogAgent.getStatus', $routeParamsResponse);
        $translation[Status::ACTION_VIEW] = $this->getRouteIfAuthorized('learningDialogAgent.getParticularResponse', $routeParamsResponse);
        $translation[Status::ACTION_MESSAGE] = $this->getRouteIfAuthorized('learningDialogAgent.postMessage', $routeParamsResponse);
        $translation[Status::ACTION_SUBMIT] = $this->getRouteIfAuthorized('learningDialogAgent.postSubmit', $routeParamsResponse);
        $translation[Status::ACTION_REOPEN] = $this->getRouteIfAuthorized('learningDialogAgent.postReopen', $routeParamsResponse);
        $translation[Status::ACTION_ASSISTANCE_REQUEST] = $this->getRouteIfAuthorized('learningDialogAgent.postAssistanceRequest', $routeParamsResponse);
        $translation[Status::ACTION_ASSISTANCE_RESOLVE] = $this->getRouteIfAuthorized('learningDialogAgent.postAssistanceResolve', $routeParamsResponse);
        $translation[Status::ACTION_RESET] = $this->getRouteIfAuthorized('learningDialogAgent.postReset', $routeParamsResponse);

        return $translation;
    }
}
