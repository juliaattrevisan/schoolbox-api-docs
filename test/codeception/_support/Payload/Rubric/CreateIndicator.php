<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Rubric;

use Payload\PostTrait;
use Payload\Request;

class CreateIndicator extends Request
{
    use PostTrait;

    /**
     * @param int      $folderId
     * @param int      $assessmentId
     * @param int|null $capabilityId
     * @param int      $capabilityIndex
     * @param string   $indicatorName
     * @param int      $levelCount
     * @param bool     $markPerCap
     * @param string   $capabilityName
     */
    public function __construct(
        int $folderId,
        int $assessmentId,
        ?int $capabilityId,
        int $capabilityIndex,
        string $indicatorName,
        int $levelCount,
        bool $markPerCap,
        string $capabilityName
    ) {
        $this->setReferer(
            $assessmentId
                ? "/learning/assessments/{$assessmentId}/modify"
                : "/learning/folders/{$folderId}/assessments/new?type=dueWork"
        );

        $this->setFormParam('type', 'criterion');
        $this->setFormParam('rubricId', $assessmentId);
        $this->setFormParam('groupId', $capabilityId === null ? '' : (string)$capabilityId);
        $this->setFormParam('groupIndex', $capabilityIndex);
        $this->setFormParam('name', $indicatorName);
        $this->setFormParam('levelCount', $levelCount);
        $this->setFormParam('folderId', $folderId);
        $this->setFormParam('markPerCapability', $markPerCap);
        $this->setFormParam('groupName', $capabilityName);
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return '/rubric';
    }
}
