<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Payload\Rubric;

use Tests\Support\Payload\PostTrait;
use Tests\Support\Payload\Request;

class CreateIndicator extends Request
{
    use PostTrait;

    /**
     * @param int      $folderId
     * @param int      $assessmentId
     * @param int|null $capabilityId
     * @param string   $indicatorName
     * @param int      $levelCount
     * @param string   $capabilityName
     */
    public function __construct(
        int $folderId,
        int $assessmentId,
        ?int $capabilityId,
        string $indicatorName,
        int $levelCount,
        string $capabilityName
    ) {
        $this->setReferer(
            $assessmentId
                ? "/learning/assessments/{$assessmentId}/modify"
                : "/learning/folders/{$folderId}/assessments/new?type=dueWork"
        );

        $this->setFormParam('type', 'indicator');
        $this->setFormParam('name', $indicatorName);
        $this->setFormParam('folderId', $folderId);
        $this->setFormParam('rubricId', $assessmentId);
        $this->setFormParam('levelCount', $levelCount);
        $this->setFormParam('capabilityId', $capabilityId);
        $this->setFormParam('capabilityName', $capabilityName);
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return '/rubric';
    }
}
