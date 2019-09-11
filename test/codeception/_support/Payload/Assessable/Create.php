<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Assessable;

use Payload\PostTrait;
use Payload\Request;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\IAssessable;

/**
 * A request to create an assessable
 */
class Create extends Request
{
    use PostTrait;
    use FormTrait;

    /** @var string */
    private $contextType;

    /** @var int */
    private $contextId;

    /**
     * @param string $contextType
     * @param int    $contextId
     * @param int    $folderId
     * @param string $assessableType
     */
    public function __construct(string $contextType, int $contextId, int $folderId, string $assessableType)
    {
        $eventTimestamp    = time();
        $this->contextType = $contextType;
        $this->contextId   = $contextId;

        $this->setReferer("/learning/{$contextType}s/{$contextId}/assessments/new?type==$assessableType");

        $this->initiateForm($assessableType);

        $this->setFormParam('part-2', 1);

        $this->setFormParam('contextType', "{$contextType}s");
        $this->setFormParam('contextId', $this->contextId);
        $this->setFormParam('folderId', $folderId);
        $this->setFormParam('assessmentType', $assessableType);
        $this->setTitle('someTitle');
        $this->setFormParam('description', '<p>This is a test assignment for automated tests</p>');
        $this->setFormParam('acceptOnline', 0);
        $this->setFormParam('publishDate', date('d/m/Y g:ia', strtotime('- 5 day', $eventTimestamp)));
        $this->setFormParam('dueDate', date('d/m/Y g:ia', strtotime('+ 10 day', $eventTimestamp)));
        if (in_array($assessableType, [AssessmentType::TYPE_QUIZ, AssessmentType::TYPE_DUE_WORK], true)) {
            $this->setFormParam('openDate', date('d/m/Y g:ia', strtotime('- 1 day', $eventTimestamp)));
            $this->setFormParam('closeDate', date('d/m/Y g:ia', strtotime('+ 12 day', $eventTimestamp)));
        }
        if ($assessableType === AssessmentType::TYPE_PROJECT) {
            $this->setFormParam('summator', 'weighted');
        }
        $this->setFormParam('weight', 14);
        if ($assessableType === AssessmentType::TYPE_DUE_WORK) {
            $this->setFormParam('self_reviewable', 'none');
        }
        $this->setFormParam('visibility', IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $this->setFormParam('markType', 1);
        $this->setFormParam('gradingScheme', 1);
        $this->setFormParam('resultset', 1);
    }


    /** @inheritDoc */
    protected function getUrl(): string
    {
        return "/learning/{$this->contextType}s/{$this->contextId}/assessments";
    }

    /** @inheritDoc */
    public function getRubricAssessmentId(): int
    {
        return 0;
    }
}
