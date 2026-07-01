<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Payload\Assessable;

use Schoolbox\Learning\Assessment\AssessmentType;

/**
 * Common implementation for the assessment create/update form
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
}
