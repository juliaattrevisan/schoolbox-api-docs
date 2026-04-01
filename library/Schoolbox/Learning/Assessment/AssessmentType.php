<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use InvalidArgumentException;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgent;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\LessonPlan\Model as LessonPlan;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Reporting\Model as Reporting;
use Schoolbox\Learning\Assessment\Reporting\ValidManageActivity;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\DialogAgent\ValidDialogAgentEnabled;
use Schoolbox\User;

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
    public const TYPE_DIALOG_AGENT = 'dialogAgent';
    public const TYPE_REPORTING = 'reporting';

    public const ALL_TYPES = [
        self::TYPE_PROJECT,
        self::TYPE_DUE_WORK,
        self::TYPE_TASK,
        self::TYPE_QUIZ,
        self::TYPE_LTI_ASSESSMENT,
        self::TYPE_LESSON_PLAN,
        self::TYPE_DIALOG_AGENT,
        self::TYPE_REPORTING,
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
        }

        if ($assessment instanceof DueWork) {
            return self::TYPE_DUE_WORK;
        }

        if ($assessment instanceof Task) {
            return self::TYPE_TASK;
        }

        if ($assessment instanceof Quiz) {
            return self::TYPE_QUIZ;
        }

        if ($assessment instanceof LtiAssessment) {
            return self::TYPE_LTI_ASSESSMENT;
        }

        if ($assessment instanceof LessonPlan) {
            return self::TYPE_LESSON_PLAN;
        }

        if ($assessment instanceof DialogAgent) {
            return self::TYPE_DIALOG_AGENT;
        }

        if ($assessment instanceof Reporting) {
            return self::TYPE_REPORTING;
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
        }

        if (self::TYPE_DUE_WORK === $stringVal) {
            $object = (new DueWork())->setContext($context);

            if ($context) {
                $object->setRubricGrid(RubricDataSource::getInstance()->retrieveGrid($object, true));
            }

            return $object;
        }

        if (self::TYPE_TASK === $stringVal) {
            return (new Task())->setContext($context);
        }

        if (self::TYPE_QUIZ === $stringVal) {
            return (new Quiz())->setContext($context);
        }

        if (self::TYPE_LTI_ASSESSMENT === $stringVal) {
            return (new LtiAssessment())->setContext($context);
        }

        if (self::TYPE_LESSON_PLAN === $stringVal) {
            return (new LessonPlan())->setContext($context);
        }

        if (self::TYPE_DIALOG_AGENT === $stringVal) {
            return (new DialogAgent())->setContext($context);
        }

        if (self::TYPE_REPORTING === $stringVal) {
            return (new Reporting())->setContext($context);
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
            case self::TYPE_DIALOG_AGENT:
                return DialogAgent::getDefaultTypeNameTranslated();
            case self::TYPE_REPORTING:
                return Reporting::getDefaultTypeNameTranslated();
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
     * Returns the list of all assessment (activity) types
     * The mapped values are the readable type names, respecting terminology, in "Title Case"
     *
     * @return array<string, string>
     */
    public static function getOptions(): array
    {
        // Bind the assessment types to their readable names
        $list = array_combine(
            self::ALL_TYPES,
            array_map(self::stringToReadableName(...), self::ALL_TYPES)
        );

        return $list;
    }


    /**
     * Returns the list of enabled assessment (activity) types
     * The mapped values are the readable type names, respecting terminology, in "Title Case"
     *
     * @param IConfigLoader $configLoader
     * @param User $user
     * @return array<string, string>
     */
    public static function getEnabledOptions(IConfigLoader $configLoader, \Schoolbox\User $user): array
    {
        $list = self::getOptions();

        /** some assessment types can be disabled, remove them from the list */
        if (!$configLoader->get(ConfigMapper::NAME_LTI_ASSESSMENTS_ENABLE)) {
            unset($list[self::TYPE_LTI_ASSESSMENT]);
        }

        /* Remove Dialog Agent if it wasn't configured properly in admin
         */
        if (!new ValidDialogAgentEnabled($configLoader)->isSatisfied()) {
            unset($list[self::TYPE_DIALOG_AGENT]);
        }

        /* Remove Reporting Activity if it was disabled in admin
         */
        if (!$configLoader->get(ConfigMapper::NAME_REPORTING_ACTIVITY_ENABLE)) {
            unset($list[self::TYPE_REPORTING]);
        }

        /* Only LMS Moderator with write+ access can create/modify/delete reporting activities
         */
        if (!(new ValidManageActivity())->isSatisfiedBy($configLoader->get(ConfigMapper::NAME_REPORTING_ACTIVITY_ENABLE), $user)) {
            unset($list[self::TYPE_REPORTING]);
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
            self::TYPE_DIALOG_AGENT => 'dialog-agent',
            self::TYPE_REPORTING => 'reporting',
            default => throw new InvalidArgumentException("Expected valid assessment type, received '$type'."),
        };
    }
}
