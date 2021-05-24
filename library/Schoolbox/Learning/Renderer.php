<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning;

use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Twig\SimpleTest;
use Twig\TwigTest;
use Twig\Extension\AbstractExtension;

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
class Renderer extends AbstractExtension
{
    /**
     * Returns a list of tests to add to the existing list.
     *
     * @return array|TwigTest[]
     */
    public function getTests()
    {
        // Intentionally no type declaration functions to avoid fatal errors in twig if wrong type passed in.
        return [
            new TwigTest('folder', function ($context) {
                return $context instanceof Folder;
            }),
            new TwigTest('unitImplementation', function ($context) {
                return $context instanceof UnitImplementation;
            }),
            new TwigTest('unitSpecification', function ($context) {
                return $context instanceof UnitSpecification;
            }),
            new TwigTest('project', function ($context) {
                return $context instanceof Project;
            }),

            new TwigTest('quiz', function ($assessable) {
                return $assessable instanceof Quiz;
            }),
            new TwigTest('dueWork', function ($assessable) {
                return $assessable instanceof DueWork;
            }),
            new TwigTest('task', function ($assessable) {
                return $assessable instanceof Task;
            }),

            new TwigTest('response', function ($interaction) {
                return $interaction instanceof IResponse;
            }),
            new TwigTest('feedback', function ($interaction) {
                return $interaction instanceof IFeedback;
            })
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
