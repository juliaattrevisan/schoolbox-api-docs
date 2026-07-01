<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Payload\Assessable;

use Tests\Support\Payload\PostTrait;
use Tests\Support\Payload\Request;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\AssessmentType;
use UnexpectedValueException;

class Update extends Request
{
    use PostTrait;
    use UpdateFormTrait;

    /** @var IAssessable */
    private $assessable;

    /**
     * @param IAssessable $assessable
     * @param string      $returnUrl
     * @throws UnexpectedValueException
     */
    public function __construct(IAssessable $assessable, string $returnUrl)
    {
        $this->assessable = $assessable;

        $this->setReferer("/learning/assessments/{$assessable->getId()}/modify?returnUrl=");

        $this->initiateForm(AssessmentType::objectToString($assessable));
        $this->populateForm($assessable, $returnUrl);

        $this->setFormParam("part-2", 1);
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return "/learning/assessments/{$this->assessable->getId()}";
    }
}
