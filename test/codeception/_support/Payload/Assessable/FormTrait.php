<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Assessable;

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
        $this->setFormParam('markType', '');
        $this->setFormParam('maxMark', '');
        $this->setFormParam('gradingScheme', '');
        $this->setFormParam('resultset', '');
        $this->setFormParam('formField', 'rb');

        foreach ([0, 1] as $index) {
            $this->setRubricLevel($index, null, '');
        }

        $this->setFormParam('curriculumCodes', '');
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->setFormParam('title', $title);
    }

    public function setRubricCapability(int $index, ?int $id, string $name): void
    {
        $assessmentId = $this->getRubricAssessmentId();

        $this->setFormParam("rb[$assessmentId][group][$index][id]", $id === null ? '' : (string)$id);
        $this->setFormParam("rb[$assessmentId][group][$index][name]", $name);
    }

    public function setRubricIndicator(int $indicatorId, int $capabilityId, string $name, bool $enabled): void
    {
        $assessmentId = $this->getRubricAssessmentId();

        $this->setFormParam("rb[$assessmentId][criterion][$indicatorId][id]", $indicatorId);
        $this->setFormParam("rb[$assessmentId][criterion][$indicatorId][groupId]", $capabilityId);
        $this->setFormParam("rb[$assessmentId][criterion][$indicatorId][name]", $name);

        if($enabled){
            $this->setFormParam("rb[$assessmentId][criterion][$indicatorId][on]", 1);
        } else {
            $this->unsetFormParam("rb[$assessmentId][criterion][$indicatorId][on]");
        }
    }

    public function setRubricLevel(int $index, ?int $id, string $name): void
    {
        $assessmentId = $this->getRubricAssessmentId();

        $this->setFormParam("rb[$assessmentId][level][$index][id]", $id === null ? '' : (string)$id);
        $this->setFormParam("rb[$assessmentId][level][$index][name]", $name);
    }

    public function setRubricDescription(
        ?int $descriptorId,
        int $indicatorId,
        int $levelIndex,
        string $descriptorText,
        ?float $descriptorPoints
    ): void {
        $assessmentId = $this->getRubricAssessmentId();

        $this->setFormParam("rb[$assessmentId][descriptor][$indicatorId][$levelIndex][id]", $descriptorId === null ? '' : (string)$descriptorId);
        $this->setFormParam("rb[$assessmentId][descriptor][$indicatorId][$levelIndex][text]", $descriptorText);
        $this->setFormParam("rb[$assessmentId][descriptor][$indicatorId][$levelIndex][points]", $descriptorPoints === null ? '' : (string)$descriptorPoints);
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
