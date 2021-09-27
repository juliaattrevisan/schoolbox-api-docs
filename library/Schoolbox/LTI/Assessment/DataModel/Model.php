<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Assessment\DataModel;

use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\AssessmentModelTrait;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * LTI assessment model.
 */
class Model implements IAssessableDataModel, ISoftDeletable
{
    use Id;
    use AssessmentModelTrait;

    /**
     * The URL of the LTI assessment: the student will be directed to this URL
     * when they attempt the assessment.
     */
    private ?string $ltiAssessmentUrl = null;

    /**
     * @return string|null
     */
    public function getLtiAssessmentUrl(): ?string
    {
        return $this->ltiAssessmentUrl;
    }

    /**
     * @param string|null $ltiAssessmentUrl
     */
    public function setLtiAssessmentUrl(?string $ltiAssessmentUrl): void
    {
        $this->ltiAssessmentUrl = $ltiAssessmentUrl;
    }

    /**
     * The LTI assessment takes its deleted status from the assessment which it
     * is composed of.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->assessmentModel->isDeleted();
    }
}
