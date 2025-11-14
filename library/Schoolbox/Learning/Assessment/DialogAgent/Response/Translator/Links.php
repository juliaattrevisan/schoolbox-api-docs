<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Response\Translator;

use Schoolbox\API\Translator\AbstractLinks;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Model;

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
            'student'    => $response->getSubmittedOnBehalfOf(),
            'studentId'    => $response->getSubmittedOnBehalfOf()->getId(),
            'response'   => $response,
            'responseId' => $response->getId(),
        ];

        $translation['view'] = $this->getRouteIfAuthorized('learningDialogAgent.getViewResponse', $routeParamsResponse);
        $translation['sobo'] = $this->getRouteIfAuthorized('learningDialogAgent.postSobo', $routeParamsResponse);
        $translation['reopen'] = $this->getRouteIfAuthorized('learningDialogAgent.postReopen', $routeParamsResponse);
        $translation['resolved'] = $this->getRouteIfAuthorized('learningDialogAgent.postAssistanceResolve', $routeParamsResponse);

        return $translation;
    }
}
