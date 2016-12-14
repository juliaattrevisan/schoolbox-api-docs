<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning;

use Schoolbox\Learning\Assessment\AbstractResponse;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Assessment\Project;

use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;

use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Task\Model as Task;

/**
 * Enables twig template 'is a' tests.
 * Usage, where 'assessment' is the var name for an IAssessable:
 * * {% if assessment.context is folder %}
 * * {% if assessment.context is unitImplementation %}
 * * {% if assessment.context is unitSpecification %}
 * * {% if assessment.context is project %}
 *
 * * {% if assessment is quiz %}
 * * {% if assessment is dueWork %}
 * * {% if assessment is task %}
 */
class Renderer extends \Twig_Extension
{
    /**
     * Returns a list of tests to add to the existing list.
     *
     * @return \Twig_SimpleTest[]
     */
    public function getTests ()
    {
        // Intentionally no type declaration functions to avoid fatal errors in twig if wrong type passed in.
        return [
            new \Twig_SimpleTest('folder', function ($context) { return $context instanceof Folder; }),
            new \Twig_SimpleTest('unitImplementation', function ($context) { return $context instanceof UnitImplementation; }),
            new \Twig_SimpleTest('unitSpecification', function ($context) { return $context instanceof UnitSpecification; }),
            new \Twig_SimpleTest('project', function ($context) { return $context instanceof Project; }),

            new \Twig_SimpleTest('quiz', function ($assessable) { return $assessable instanceof Quiz; }),
            new \Twig_SimpleTest('dueWork', function ($assessable) { return $assessable instanceof DueWork; }),
            new \Twig_SimpleTest('task', function ($assessable) { return $assessable instanceof Task; }),

            new \Twig_SimpleTest('response', function ($interaction) { return $interaction instanceof IResponse; }),
            new \Twig_SimpleTest('feedback', function ($interaction) { return $interaction instanceof IFeedback; })
        ];
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'assessment_tests';
    }
}