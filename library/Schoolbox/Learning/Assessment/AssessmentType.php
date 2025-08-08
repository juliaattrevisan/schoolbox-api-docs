<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use InvalidArgumentException;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\LessonPlan\Model as LessonPlan;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\AssessmentContext;

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
    use TypeTrait;

    // Template Directories / URL Fragments / Radio Values
    // (for now, these are both the same values. Could trivially split into use-case-specific values if desired)
    public const TYPE_PROJECT = 'project';
    public const TYPE_DUE_WORK = 'dueWork';
    public const TYPE_TASK = 'task';
    public const TYPE_QUIZ = 'quiz';
    public const TYPE_LTI_ASSESSMENT = 'lti';
    public const TYPE_LESSON_PLAN = 'lessonPlan';

    public const ALL_TYPES = [
        self::TYPE_PROJECT,
        self::TYPE_DUE_WORK,
        self::TYPE_TASK,
        self::TYPE_QUIZ,
        self::TYPE_LTI_ASSESSMENT,
        self::TYPE_LESSON_PLAN
    ];

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
        } elseif ($assessment instanceof LessonPlan) {
            return self::TYPE_LESSON_PLAN;
        }
        throw new \UnexpectedValueException('Unknown IAssessable: ' . get_class($assessment));
    }


    /**
     * Get the numeric representation from string representation
     *
     * @param string                 $stringVal
     * @param AssessmentContext|null $context
     * @return IAssessable
     */
    public static function stringToObject($stringVal, ?AssessmentContext $context = null)
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
        } elseif (self::TYPE_LESSON_PLAN === $stringVal) {
            return (new \Schoolbox\Learning\Assessment\LessonPlan\Model())->setContext($context);
        }

        throw new \UnexpectedValueException("Unknown Assessment: $stringVal");
    }

    /**
     * Get the readable type name (translated) from a string representation
     * @param string $stringVal e.g. 'dueWork'
     * @return string e.g. 'Due Work'
     */
    public static function stringToReadableName(string $stringVal): string
    {
        switch ($stringVal) {
            case self::TYPE_PROJECT:
                return Project::getDefaultTypeNameTranslated();
            case self::TYPE_DUE_WORK:
                return DueWork::getDefaultTypeNameTranslated();
            case self::TYPE_TASK:
                return Task::getDefaultTypeNameTranslated();
            case self::TYPE_QUIZ:
                return Quiz::getDefaultTypeNameTranslated();
            case self::TYPE_LTI_ASSESSMENT:
                return LtiAssessment::getDefaultTypeNameTranslated();
            case self::TYPE_LESSON_PLAN:
                return LessonPlan::getDefaultTypeNameTranslated();
            default:
                throw new InvalidArgumentException("Unknown assessment type: $stringVal");
        }
    }

    /* Getters/Setters
     */

    /**
     * Returns the available string values
     * @return string[]
     */
    protected function toArray()
    {
        return self::ALL_TYPES;
    }

    /**
     * Returns the list of enabled assessment (activity) types
     * The mapped values are the readable type names, respecting terminology, in "Title Case"
     *
     * @param bool $ltiEnabled      // Is LTI Enabled? check ConfigMapper::NAME_LTI_ASSESSMENTS_ENABLE
     * @return array<string, string>
     */
    public static function getOptions(bool $ltiEnabled): array
    {
        // Bind the assessment types to their readable names
        $list = array_combine(
            self::ALL_TYPES,
            array_map(
                fn($type) => self::stringToReadableName($type),
                self::ALL_TYPES,
            )
        );

        // if LTI is not enabled then remove that entry
        if (!$ltiEnabled) {
            unset($list['lti']);
        }

        return $list;
    }

    /**
     * Return the part of the icon class after 'icon-'.  E.g. for 'icon-due-work', return 'due-work'.
     *
     * @param $type
     * @return string
     */
    public static function getIconClassSuffixForType($type): string
    {
        return match ($type) {
            self::TYPE_DUE_WORK => 'due-work',
            self::TYPE_QUIZ => 'quiz',
            self::TYPE_TASK => 'task',
            self::TYPE_PROJECT => 'project',
            self::TYPE_LTI_ASSESSMENT => 'lti',
            self::TYPE_LESSON_PLAN => 'lesson-plan',
            default => throw new InvalidArgumentException("Expected valid assessment type, received '$type'."),
        };
    }
}
