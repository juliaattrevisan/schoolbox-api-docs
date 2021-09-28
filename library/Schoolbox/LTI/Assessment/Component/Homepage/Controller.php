<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Assessment\Component\Homepage;

use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\Component\Homepage\Controller as BaseAssessmentController;

class Controller extends BaseAssessmentController
{
    /**
     * @return string
     */
    protected function getTwigTemplate()
    {
        return '@ltiAssessmentComponent/component.twig';
    }

    /**
     * @return string
     */
    protected function getAssessmentType()
    {
        return AssessmentType::TYPE_LTI_ASSESSMENT;
    }
}
