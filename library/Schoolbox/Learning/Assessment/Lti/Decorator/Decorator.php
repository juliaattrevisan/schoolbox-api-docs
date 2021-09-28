<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti\Decorator;

use Schoolbox\Learning\Assessment\DecoratorInterface;
use Schoolbox\LTI\Assessment\Model;

class Decorator implements DecoratorInterface
{
    use LtiTrait;

    /**
     * Constructor
     *
     * @param string|null $url
     */
    public function __construct(?string $url)
    {
        $this->setLtiAssessmentUrl($url);
    }

    /**
     * @param Model $assessment
     */
    public function decorate($assessment)
    {
        $assessment->setLtiAssessmentUrl($this->ltiAssessmentUrl);
    }
}
