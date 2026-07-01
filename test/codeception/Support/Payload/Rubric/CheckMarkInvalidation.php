<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Payload\Rubric;

use Tests\Support\Payload;
use Tests\Support\Payload\PostTrait;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\AssessmentType;
use UnexpectedValueException;

/**
 * A request to check whether a rubric structure is about to change and
 * therefore requires mark invalidation.
 */
class CheckMarkInvalidation extends Payload\Request
{
    use PostTrait;
    use Payload\Assessable\UpdateFormTrait;

    /**
     * @param IAssessable $assessable
     * @param string      $returnUrl
     * @throws UnexpectedValueException
     */
    public function __construct(IAssessable $assessable, string $returnUrl)
    {
        $this->setReferer("/learning/assessments/{$assessable->getId()}/modify?returnUrl=");

        $this->initiateForm(AssessmentType::objectToString($assessable));
        $this->populateForm($assessable, $returnUrl);
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return '/rubric/determineMarkInvalidation';
    }
}
