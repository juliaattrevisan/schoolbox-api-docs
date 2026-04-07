<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Payload\Assessable;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Learning\Assessment\AssessmentType;

/**
 * Common implementation for the assessment create/update form
 * (also used by the rubric check invalidation request)
 */
trait FormTrait
{
    /**
     * Set a requests form param
     * @see \Payload\Request::setFormParam()
     *
     * @param string $name
     * @param        $value
     */
    abstract public function setFormParam(string $name, $value): void;

    /**
     * Unsets a post param if it exists
     * @see \Payload\Request::unsetFormParam()
     *
     * @param string $name
     */
    abstract protected function unsetFormParam(string $name): void;

    /**
     * Initiate an empty form payload
     *
     * @param string $assessableType
     */
    private function initiateForm(string $assessableType): void
    {
        $this->setFormParam('folderId', '');
        $this->setTitle('');
        $this->setFormParam('workType', '');
        $this->setFormParam('description', '');
        $this->setFormParam('acceptOnline', '');
        $this->setFormParam('publishDate', '');
        $this->setFormParam('dueDate', '');
        $this->setFormParam('openDate', '');
        $this->setFormParam('closeDate', '');
        if ($assessableType === AssessmentType::TYPE_PROJECT) {
            $this->setFormParam('summator', '');
        }
        $this->setFormParam('weight', 14);
        if ($assessableType === AssessmentType::TYPE_DUE_WORK) {
            $this->setFormParam('self_reviewable', '');
        }
        $this->setFormParam('visibility', '');
        $this->setFormParam('markType', 1);
        $this->setFormParam('maxMark', '');
        $this->setFormParam('gradingScheme', 1);
        if ($assessableType === AssessmentType::TYPE_DUE_WORK) {
            $this->setFormParam('instructor-maxMark', '');
            $this->setFormParam('instructor-markType', 1);
            $this->setFormParam('instructor-gradingScheme', 1);
        }
        $this->setFormParam('resultset', '');

        $this->setFormParam('curriculumCodes', '');
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->setFormParam('title', $title);
    }

    private function getRubricFormParam(): ?array
    {
        $assessmentId = $this->getRubricAssessmentId();

        $value = $this->getFormParam("rubric[$assessmentId]") ?? null;
        if (!empty($value)) {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }

        // no rubric yet, add one and populate it with defaults
        // TODO: this will change again when testing multiple rubrics
        $this->setRubricFormParam([
            'level' => [],
            'group' => [],
            'criterion' => [],
            'descriptor' => [],
            'title' => '',
            'weight' => null,
            'useMarkRange' => false,
            'capabilityMarkType' => MarkType::SLUG_NONE,
            'capabilityGradeGroup' => 1,
        ]);

        foreach ([0, 1] as $index) {
            $this->setRubricLevel($index, null, '');
        }

        return $this->getRubricFormParam();
    }

    private function setRubricFormParam(array $rubricData): void
    {
        $assessmentId = $this->getRubricAssessmentId();
        $this->setFormParam("rubric[$assessmentId]", json_encode($rubricData, JSON_THROW_ON_ERROR));
    }

    public function setRubricCapability(int $index, ?int $id, string $name, ?int $maxMark): void
    {
        $rubricData = $this->getRubricFormParam();

        $rubricData['group'][$index]['maxValue'] = $maxMark === null ? '' : (string)$maxMark;
        $rubricData['group'][$index]['id'] = $id === null ? '' : (string)$id;
        $rubricData['group'][$index]['name'] = $name;

        $this->setRubricFormParam($rubricData);
    }

    public function setRubricTitle(string $title): void
    {
        $rubricData = $this->getRubricFormParam();
        $rubricData['title'] = $title;
        $this->setRubricFormParam($rubricData);
    }

    public function setRubricWeight(?float $weight): void
    {
        $rubricData = $this->getRubricFormParam();
        $rubricData['weight'] = $weight;
        $this->setRubricFormParam($rubricData);
    }

    public function setRubricCapabilityMarkType(?string $markTypeSlug): void
    {
        $rubricData = $this->getRubricFormParam();
        $rubricData['capabilityMarkType'] = $markTypeSlug;
        $this->setRubricFormParam($rubricData);
    }

    public function setRubricCapabilityGradeGroup(?int $gradeGroupId): void
    {
        $rubricData = $this->getRubricFormParam();
        $rubricData['capabilityGradeGroup'] = $gradeGroupId;
        $this->setRubricFormParam($rubricData);
    }

    public function setRubricIndicator(int $indicatorId, int $capabilityId, string $name, bool $enabled): void
    {
        $rubricData = $this->getRubricFormParam();

        $rubricData['criterion'][$indicatorId]['id'] = $indicatorId;
        $rubricData['criterion'][$indicatorId]['groupId'] = $capabilityId;
        $rubricData['criterion'][$indicatorId]['name'] = $name;

        if ($enabled) {
            $rubricData['criterion'][$indicatorId]['on'] = 1;
        } else {
            unset($rubricData['criterion'][$indicatorId]['on']);
        }

        $this->setRubricFormParam($rubricData);
    }

    public function setRubricLevel(int $index, ?int $id, string $name): void
    {
        $rubricData = $this->getRubricFormParam();

        $rubricData['level'][$index]['id'] = $id === null ? '' : (string)$id;
        $rubricData['level'][$index]['name'] = $name;

        $this->setRubricFormParam($rubricData);
    }

    public function setRubricDescription(
        ?int $descriptorId,
        int $indicatorId,
        int $levelIndex,
        string $descriptorText,
        ?float $descriptorPoints
    ): void {
        $rubricData = $this->getRubricFormParam();

        $rubricData['descriptor'][$indicatorId][$levelIndex]['id'] = $descriptorId === null ? '' : (string)$descriptorId;
        $rubricData['descriptor'][$indicatorId][$levelIndex]['text'] = $descriptorText;
        $rubricData['descriptor'][$indicatorId][$levelIndex]['points'] = $descriptorPoints === null ? '' : (string)$descriptorPoints;

        $this->setRubricFormParam($rubricData);
    }

    /**
     * Return the value that identifies the assessment in the rubric data payload
     *
     * On assessment create, this should be 0. For update, the id of the assessment.
     *
     * @return int
     */
    abstract public function getRubricAssessmentId(): int;
}
