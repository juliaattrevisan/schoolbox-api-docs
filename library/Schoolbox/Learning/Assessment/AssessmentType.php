<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\LTI\Assessment\Model as LtiAssessment;

/**
 * Encapsulates the mapping between Assessment Types and:
 * * URL fragments
 * * Twig template directories, and
 * * Radio button values in twig
 *
 * This class essentially enumerates the concrete implementations of IAssessable
 * Does not map to DB table as that relationship is represented in join tables
 *
 * Does not belong on IAssessable as interface cannot have behaviour (and shouldn't know about implementors)
 * Does not belong on AbstractAssessment as parent class should not know about children
 */
class AssessmentType implements IType
{
    // Template Directories / URL Fragments / Radio Values
    // (for now, these are both the same values. Could trivially split into use-case-specific values if desired)
    const TYPE_PROJECT = 'project';
    const TYPE_DUE_WORK = 'dueWork';
    const TYPE_TASK = 'task';
    const TYPE_QUIZ = 'quiz';
    const TYPE_LTI_ASSESSMENT = 'ltiAssessment';

    use TypeTrait;

    /**
     * Get twig template directory for a given IAssessable
     *
     * @param IAssessable $assessment
     * @return string
     */
    public static function objectToString(IAssessable $assessment)
    {
        if ($assessment instanceof Project) {
            return self::TYPE_PROJECT;
        } elseif ($assessment instanceof DueWork) {
            return self::TYPE_DUE_WORK;
        } elseif ($assessment instanceof Task) {
            return self::TYPE_TASK;
        } elseif ($assessment instanceof Quiz) {
            return self::TYPE_QUIZ;
        } elseif ($assessment instanceof LtiAssessment) {
            return self::TYPE_LTI_ASSESSMENT;
        }
        throw new \UnexpectedValueException('Unknown IAssessable: ' . get_class($assessment));
    }


    /**
     * Get the numeric representation from string representation
     *
     * @param string            $stringVal
     * @param AssessmentContext $context
     * @return IAssessable
     */
    public static function stringToObject($stringVal, AssessmentContext $context = null)
    {
        if (self::TYPE_PROJECT === $stringVal) {
            return (new Project())->setContext($context);
        } elseif (self::TYPE_DUE_WORK === $stringVal) {
            $object = (new DueWork())->setContext($context);

            if ($context) {
                $object->setRubricGrid(RubricDataSource::getInstance()->retrieveGrid($object, true));
            }

            return $object;
        } elseif (self::TYPE_TASK === $stringVal) {
            return (new Task())->setContext($context);
        } elseif (self::TYPE_QUIZ === $stringVal) {
            return (new Quiz())->setContext($context);
        } elseif (self::TYPE_LTI_ASSESSMENT === $stringVal) {
            return (new LtiAssessment())->setContext($context);
        }

        throw new \UnexpectedValueException("Unknown Assessment: $stringVal");
    }

    /* Getters/Setters
     */

    /**
     * Returns the available string values
     * @return string[]
     */
    protected function toArray()
    {
        return [
            self::TYPE_PROJECT,
            self::TYPE_DUE_WORK,
            self::TYPE_TASK,
            self::TYPE_QUIZ,
            self::TYPE_LTI_ASSESSMENT,
        ];
    }
}
