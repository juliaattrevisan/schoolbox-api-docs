<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

/**
 * Encapsulates the mapping between Assessment Types and their URL fragments
 *
 * This class essentially enumerates the concrete implementations of IAssessable
 * Does not map to DB table as that relationship is represented in join tables
 *
 * Does not belong on IAssessable as interface cannot have behaviour
 * Does not belong on AbstractAssessment as parent class should not know about children
 */
class AssessmentType
{
    /**
     * Assessable types
     */
    const PROJECT = 1;
    const DUE_WORK = 2;
    const TASK = 3;
    const QUIZ = 4;

    /**
     * Set of key-value pairs
     *
     * These should only be used on parsing / rendering of URLs.
     * They should not be used internally, use the constants for that.
     *
     * @var string[] $urlFragments
     */
    private static $urlFragments = [
        self::PROJECT  => 'project',
        self::DUE_WORK => 'dueWork',
        self::TASK     => 'task',
        self::QUIZ     => 'quiz',
    ];

    /**
     * Get the string representation from numeric representation
     *
     * @param int $assessmentType
     * @return string
     */
    public static function getUrlFragment($assessmentType)
    {
        return self::$urlFragments[$assessmentType];
    }

    /**
     * Get the numeric representation from string representation
     *
     * @param string $urlFragment
     * @return int
     */
    public static function getAssessmentType($urlFragment)
    {
        $flipped = array_flip(self::$urlFragments);

        return $flipped[$urlFragment];
    }

    /**
     * Is the $urlFragment valid?
     *
     * @param string $urlFragment
     * @return bool
     */
    public static function isValidUrlFragment($urlFragment)
    {
        if (strlen($urlFragment)) {
            return in_array($urlFragment, array_values(self::$urlFragments));
        }

        return false;
    }

    /**
     * Is the $assessmentType valid?
     *
     * @param int $assessmentType
     * @return bool
     */
    public static function isValidIndex($assessmentType)
    {
        if ($assessmentType !== null) {
            return in_array($assessmentType, array_keys(self::$urlFragments));
        }

        return false;
    }

    /**
     * $index is a project assessment type
     *
     * @param int $assessmentType
     * @return bool
     */
    public static function isProject($assessmentType)
    {
        return $assessmentType == self::PROJECT;
    }

    /**
     * $index is a due work assessment type
     *
     * @param int $assessmentType
     * @return bool
     */
    public static function isDueWork($assessmentType)
    {
        return $assessmentType == self::DUE_WORK;
    }

    /**
     * $index is a quiz assessment type
     *
     * @param int $assessmentType
     * @return bool
     */
    public static function isQuiz($assessmentType)
    {
        return $assessmentType == self::QUIZ;
    }

    /**
     * $index is a task assessment type
     *
     * @param int $assessmentType
     * @return bool
     */
    public static function isTask($assessmentType)
    {
        return $assessmentType == self::TASK;
    }

    /* Getters/Setters
     */

    /**
     * Maps ID:int => UrlFragment:string
     * @return array
     */
    public static function getUrlFragments()
    {
        return self::$urlFragments;
    }
}