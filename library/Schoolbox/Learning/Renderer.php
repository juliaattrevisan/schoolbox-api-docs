<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning;

use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Learning\Assessment\Project;

/**
 * Enables twig template 'is a' tests.
 * Usage:
 * * {% if assessment.context is a folder %}
 * * {% if assessment.context is a unitImplementation %}
 * * {% if assessment.context is a unitSpecification %}
 * * {% if assessment.context is a project %}
 */
class Renderer extends \Twig_Extension
{
    public function getTests ()
    {
        return [
            new \Twig_SimpleTest('folder', function (AssessmentContext $context) { return $context instanceof Folder; }),
            new \Twig_SimpleTest('unitImplementation', function (AssessmentContext $context) { return $context instanceof UnitImplementation; }),
            new \Twig_SimpleTest('unitSpecification', function (AssessmentContext $context) { return $context instanceof UnitSpecification; }),
            new \Twig_SimpleTest('project', function (AssessmentContext $context) { return $context instanceof Project; })
        ];
    }

    public function getName()
    {
        return 'assessment_context_tests';
    }
}