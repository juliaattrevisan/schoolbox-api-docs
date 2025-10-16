<?php

/**
 * Dialog Agent homepage component controller
 *
 * @author      Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent\Component\Homepage;

use Schoolbox\Learning\Assessment\AssessmentType;

class Controller extends \Schoolbox\Learning\Assessment\Component\Homepage\Controller
{
    /**
     * @return string
     */
    protected function getTwigTemplate()
    {
        return '@learningAssessment/dialogAgent/component.twig';
    }

    /**
     * @return string
     */
    protected function getAssessmentType()
    {
        return AssessmentType::TYPE_DIALOG_AGENT;
    }
}
