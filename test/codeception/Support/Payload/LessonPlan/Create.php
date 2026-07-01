<?php

namespace Tests\Support\Payload\LessonPlan;

use Schoolbox\Learning\Assessment\AssessmentType;
use Tests\Support\Payload\Assessable\FormTrait;
use Tests\Support\Payload\PostTrait;
use Tests\Support\Payload\Request;

class Create extends Request
{
    use PostTrait;
    use FormTrait;

    /** @var string */
    private string $contextType;

    /** @var int */
    private int $contextId;

    /**
     * @param string $contextType
     * @param int    $contextId
     * @param int    $folderId
     * @param int $lessonPlanTemplateId
     * @param string $title
     * @param array $lessons
     * @param array $visibility
     * lesson plan visibility options are: 'staff' (not visible to students) or 'on' (visible to students)
     */
    public function __construct(string $contextType, int $contextId, int $folderId, int $lessonPlanTemplateId, string $title, array $lessons, array $visibility)
    {
        $eventTimestamp    = time();
        $this->contextType = $contextType;
        $this->contextId   = $contextId;

        $this->setReferer("/learning/{$contextType}s/{$contextId}/assessments/new?lpt=={$lessonPlanTemplateId}&type=lessonPlan");


        $this->setFormParam('contextType', "{$contextType}s");
        $this->setFormParam('contextId', $this->contextId);
        $this->setFormParam('folderId', $folderId);
        $this->setFormParam('assessmentType', AssessmentType::TYPE_LESSON_PLAN);
        $this->setFormParam('lessonPlanTemplateId', $lessonPlanTemplateId);
        $this->setFormParam('title', $title);
        $this->setFormParam('dueDate', date('d/m/Y g:ia', strtotime('+ 10 day', $eventTimestamp)));

        for ($i = 1; $i <= count($lessons); $i++) {
            $this->setFormParam('field[new' . $i . '][template]', '' . $i . '');
            $this->setFormParam('field[new' . $i . '][body]', "test");
        }

        $this->setFormParam('lessonPlanVisibility', $visibility[0]);
        $this->setFormParam('publishDate', date('d/m/Y g:ia', strtotime('- 5 day', $eventTimestamp)));
    }


    /** @inheritDoc */
    protected function getUrl(): string
    {
        return "/learning/{$this->contextType}s/{$this->contextId}/assessments";
    }
}
