<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Rubric;

use Payload\PostTrait;
use Payload\Request;

/**
 * A request to create a capability
 */
class CreateCapability extends Request
{
    use PostTrait;

    /**
     * @param int    $folderId
     * @param int    $assessmentId
     * @param int    $capabilityIndex
     * @param string $capabilityName
     * @param int    $levelCount
     * @param bool   $markPerCap
     */
    public function __construct(
        int $folderId,
        int $assessmentId,
        int $capabilityIndex,
        string $capabilityName,
        int $levelCount,
        bool $markPerCap
    ) {
        $this->setReferer(
            $assessmentId
                ? "/learning/assessments/{$assessmentId}/modify"
                : "/learning/folders/{$folderId}/assessments/new?type=dueWork"
        );

        $this->setFormParam('type', 'group');
        $this->setFormParam('rubricId', $assessmentId);
        $this->setFormParam('groupId', '');
        $this->setFormParam('groupIndex', $capabilityIndex);
        $this->setFormParam('name', $capabilityName);
        $this->setFormParam('levelCount', $levelCount);
        $this->setFormParam('folderId', $folderId);
        $this->setFormParam('markPerCapability', $markPerCap);
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return '/rubric';
    }
}



