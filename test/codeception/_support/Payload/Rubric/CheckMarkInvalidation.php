<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Rubric;

use Payload;
use Payload\PostTrait;
use Schoolbox\Learning\Assessment\IAssessable;
use UnexpectedValueException;

/**
 * A request to check whether a rubric structure is about to change and
 * therefore requires mark invalidation.
 */
class CheckMarkInvalidation extends Payload\Request
{
    use PostTrait;
    use Payload\Assessable\UpdateFormTrait;

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

        $this->initiateForm($assessable::getDefaultTypeName());
        $this->populateForm($assessable, $returnUrl);
    }

    public function getRubricAssessmentId(): int
    {
        return $this->assessable->getId();
    }

    /** @inheritDoc */
    protected function getUrl(): string
    {
        return '/rubric/determineMarkInvalidation';
    }
}
