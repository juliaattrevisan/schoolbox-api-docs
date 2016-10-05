<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Learning\Assessment\Project;

use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;

use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Task\Model as Task;

/**
 * Enables twig template 'is a' tests.
 * Usage:
 * * {% if assessment.context is a folder %}
 * * {% if assessment.context is a unitImplementation %}
 * * {% if assessment.context is a unitSpecification %}
 * * {% if assessment.context is a project %}
 *
 * * {% if assessment is a quiz %}
 * * {% if assessment is a dueWork %}
 * * {% if assessment is a task %}
 */
class Renderer extends \Twig_Extension
{
    public function getTests ()
    {
        return [
            new \Twig_SimpleTest('folder', function (AssessmentContext $context) { return $context instanceof Folder; }),
            new \Twig_SimpleTest('unitImplementation', function (AssessmentContext $context) { return $context instanceof UnitImplementation; }),
            new \Twig_SimpleTest('unitSpecification', function (AssessmentContext $context) { return $context instanceof UnitSpecification; }),
            new \Twig_SimpleTest('project', function (AssessmentContext $context) { return $context instanceof Project; }),

            new \Twig_SimpleTest('quiz', function (IAssessable $assessable) { return $assessable instanceof Quiz; }),
            new \Twig_SimpleTest('dueWork', function (IAssessable $assessable) { return $assessable instanceof DueWork; }),
            new \Twig_SimpleTest('task', function (IAssessable $assessable) { return $assessable instanceof Task; })
        ];
    }

    public function getName()
    {
        return 'assessment_tests';
    }
}