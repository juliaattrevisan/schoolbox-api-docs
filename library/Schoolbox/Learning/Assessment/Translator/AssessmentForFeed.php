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
class AssessmentForFeed
{
    /**
     * @param IAssessable $assessment
     * @return array
     */
    public function __invoke(IAssessable $assessment): array
    {
        return [
            'id'          => $assessment->getId(),
            'title'       => $assessment->getTitle(),
            'publishedAt' => $assessment->getPublishAt()?->getUsingFormat(DATE_ATOM),
            'openAt'      => $assessment->getOpenAt()?->getUsingFormat(DATE_ATOM),
            'dueAt'       => $assessment->getDueAt()?->getUsingFormat(DATE_ATOM),
            'closeAt'     => $assessment->getCloseAt()?->getUsingFormat(DATE_ATOM),
            'weight'      => $assessment->getWeight(),
            'sequence'    => $assessment->getSequence(),
            'workType'    => $assessment->getWorkType()?->getName(),
            'folderId'    => $assessment->getContext()->getFolder()->getId(),
            'folderTitle' => $assessment->getContext()->getFolder()->getNameWithCodes(),
        ];
    }
}
