<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Translator;

use Schoolbox\API\Translator\AbstractLinks;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;

/**
 * Class Links
 *
 * Set up the _links node with a bunch of permission-based urls that
 * $user can access
 */
class Links extends AbstractLinks
{
    /**
     * @param IAssessable $assessment
     * @param User        $student
     * @return array
     */
    public function __invoke(IAssessable $assessment, User $student): array
    {
        $translation = [];

        $translation['summary'] = $this->getRouteIfAuthorized(
            'learningAssessment.getSummary',
            [
                'assessmentId' => $assessment->getId(),
                'studentId'    => $student->getId(),
                'assessment'   => $assessment,
                'student'      => $student,
            ]
        );
        return $translation;
    }
}
