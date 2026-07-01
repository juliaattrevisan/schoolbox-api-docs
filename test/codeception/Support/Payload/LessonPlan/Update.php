<?php

namespace Tests\Support\Payload\LessonPlan;

use Schoolbox\Learning\Assessment\AssessmentType;
use Tests\Support\Payload\Assessable\FormTrait;
use Tests\Support\Payload\PostTrait;
use Tests\Support\Payload\Request;

class Update extends Request
{
    use PostTrait;
    use FormTrait;

    /** @var int */
    private int $lessonPlanId;

    /**
     * @param int $folderId
     * @param int $lessonPlanId
     * @param int $lessonPlanTemplateId
     * @param string $title
     * @param array $visibility
     * lesson plan visibility options are: 'staff' (not visible to students) or 'on' (visible to students)
     */
    public function __construct(int $lessonPlanId, int $folderId, int $lessonPlanTemplateId, string $title, array $visibility)
    {
        $this->lessonPlanId = $lessonPlanId;
        $eventTimestamp    = time();

        $this->setReferer("/learning/assessments/{$lessonPlanId}/modify");

        $this->setFormParam('assessmentType', AssessmentType::TYPE_LESSON_PLAN);

        $this->setFormParam('lessonPlanId', $lessonPlanId);
        $this->setFormParam('folderId', $folderId);
        $this->setFormParam('lessonPlanTemplateId', $lessonPlanTemplateId);
        $this->setFormParam('title', $title);
        $this->setFormParam('dueDate', date('d/m/Y g:ia', strtotime('+ 10 day', $eventTimestamp)));

        $this->setFormParam('lessonPlanVisibility', $visibility[0]);
        $this->setFormParam('publishDate', date('d/m/Y g:ia', strtotime('- 5 day', $eventTimestamp)));
    }


    /** @inheritDoc */
    protected function getUrl(): string
    {
        return "/learning/assessments/{$this->lessonPlanId}";
    }
}
