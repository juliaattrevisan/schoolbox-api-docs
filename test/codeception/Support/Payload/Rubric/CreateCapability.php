<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Payload\Rubric;

use Tests\Support\Payload\PostTrait;
use Tests\Support\Payload\Request;

/**
 * A request to create a capability
 */
class CreateCapability extends Request
{
    use PostTrait;

    /**
     * @param int    $folderId
     * @param int    $assessmentId
     * @param string $capabilityName
     * @param int    $levelCount
     */
    public function __construct(
        int $folderId,
        int $assessmentId,
        string $capabilityName,
        int $levelCount
    ) {
        $this->setReferer(
            $assessmentId
                ? "/learning/assessments/{$assessmentId}/modify"
                : "/learning/folders/{$folderId}/assessments/new?type=dueWork"
        );

        $this->setFormParam('type', 'capability');
        $this->setFormParam('name', $capabilityName);
        $this->setFormParam('folderId', $folderId);
        $this->setFormParam('rubricId', $assessmentId);
        $this->setFormParam('levelCount', $levelCount);
        $this->setFormParam('capabilityId', null);
        $this->setFormParam('capabilityName', null);
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return '/rubric';
    }
}
