<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Translator;

use Schoolbox\Learning\Assessment\IAssessable;

/**
 * Translator for translating an assessment model into an array for feed.
 */
class UnitAssessments
{
    /**
     * @param IAssessable $assessment
     * @return array
     */
    public function __invoke(IAssessable $assessment): array
    {
        return [
            'id'             => $assessment->getId(),
            'title'          => $assessment->getTitle(),
            'assessmentType' => $assessment->getFamilyName(),
            'closeAt'        => $assessment->getCloseAt()?->getUsingFormat(DATE_ATOM),
        ];
    }
}
