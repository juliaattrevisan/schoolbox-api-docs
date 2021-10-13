<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti\Decorator;

use Schoolbox\Learning\Assessment\DecoratorInterface;
use Schoolbox\Learning\Assessment\Lti\Model;
use Schoolbox\LTI\Provider\Model as LtiTool;

class Decorator implements DecoratorInterface
{
    use LtiTrait;

    /**
     * Constructor
     *
     * @param string|null $url
     * @param int|null    $toolId
     * @param array|null  $customFields
     * @param string|null $lineItemResourceId
     * @param float|null  $lineItemScoreMaximum
     */
    public function __construct(
        ?string $url,
        ?int $toolId,
        ?array $customFields,
        ?string $lineItemResourceId,
        ?float $lineItemScoreMaximum
    ) {
        $this->setLtiAssessmentUrl($url);
        $this->setToolId($toolId);
        $this->setCustomFields($customFields);
        $this->setLineItemResourceId($lineItemResourceId);
        $this->setLineItemScoreMaximum($lineItemScoreMaximum);
    }

    /**
     * @param Model $assessment
     */
    public function decorate($assessment)
    {
        $assessment
            ->setLtiAssessmentUrl($this->ltiAssessmentUrl)
            ->setLtiTool((new LtiTool())->setId($this->toolId))
            ->setCustomFields($this->customFields)
            ->setLineItemResourceId($this->lineItemResourceId)
            ->setLineItemScoreMaximum($this->lineItemScoreMaximum);
    }
}
