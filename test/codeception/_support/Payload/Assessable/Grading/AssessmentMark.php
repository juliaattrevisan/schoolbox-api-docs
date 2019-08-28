<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Assessable\Grading;

use Payload\PostTrait;
use Payload\Request;

class AssessmentMark extends Request
{
    use PostTrait;

    /** @var int */
    private $assessmentId;

    /** @var int */
    private $studentId;

    /** @var int|null */
    private $submissionId;

    /**
     * @param int      $assessmentId
     * @param int      $studentId
     * @param int|null $submissionId
     */
    public function __construct(int $assessmentId, int $studentId, ?int $submissionId)
    {
        $this->assessmentId = $assessmentId;
        $this->studentId    = $studentId;
        $this->submissionId = $submissionId;

        $referer = $submissionId === null
            ? "/learning/assessments/$assessmentId/$studentId/responses"
            : "/learning/assessments/$assessmentId/$studentId/responses/$submissionId";
        $this->setReferer($referer);

        $this->setFormParam('responseId', $submissionId === null ? '' : (string)$submissionId);
        $this->setFormParam('fromInlineGrader', true);
        $this->setFormParam('mark', '75');
        $this->setFormParam('comment', 'Some Feedback for Student');
        $this->setFormParam('publish', '1');
    }

    public function getAssessmentId(): int
    {
        return $this->assessmentId;
    }

    public function addMarkRubricDescriptorSelect(int $descriptorId): void
    {
        $this->setFormParam("rubric[descriptors][$descriptorId][selected]", 1);
    }

    protected function getUrl(): string
    {
        return $this->submissionId === null
            ? "/learning/assessments/{$this->assessmentId}/{$this->studentId}/feedback"
            : "/learning/assessments/{$this->assessmentId}/{$this->studentId}/{$this->submissionId}/feedback";
    }
}
