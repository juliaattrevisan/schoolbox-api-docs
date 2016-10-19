<?php
/**
 * Assessment Types
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

class Types
{
    /**
     * Assessable types
     */
    const ASSESSMENT_TYPE_PROJECT = 1;
    const ASSESSMENT_TYPE_DUE_WORK = 2;
    const ASSESSMENT_TYPE_TASK = 3;
    const ASSESSMENT_TYPE_QUIZ = 4;

    /**
     * Set of key-value pairs
     *
     * These should only be used on parsing / rendering of URLs.
     * They should not be used internally, use the constants for that.
     *
     * @var string[] $urlFragments
     */
    private static $urlFragments = [
        self::ASSESSMENT_TYPE_PROJECT  => 'project',
        self::ASSESSMENT_TYPE_DUE_WORK => 'dueWork',
        self::ASSESSMENT_TYPE_TASK     => 'task',
        self::ASSESSMENT_TYPE_QUIZ     => 'quiz',
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
     * Is the $Key valid?
     *
     * @param string $key
     * @return bool
     */
    public static function isValidKey($key)
    {
        if (strlen($key)) {
            return in_array($key, array_values(self::$urlFragments));
        }

        return false;
    }

    /**
     * Is the $index valid?
     *
     * @param int $index
     * @return bool
     */
    public static function isValidIndex($index)
    {
        if ($index !== null) {
            return in_array($index, array_keys(self::$urlFragments));
        }

        return false;
    }

    /**
     * $index is a project assessment type
     *
     * @param $index
     * @return bool
     */
    public static function isProject($index)
    {
        return $index == self::ASSESSMENT_TYPE_PROJECT;
    }

    /**
     * $index is a due work assessment type
     *
     * @param $index
     * @return bool
     */
    public static function isDueWork($index)
    {
        return $index == self::ASSESSMENT_TYPE_DUE_WORK;
    }

    /**
     * $index is a quiz assessment type
     *
     * @param $index
     * @return bool
     */
    public static function isQuiz($index)
    {
        return $index == self::ASSESSMENT_TYPE_QUIZ;
    }

    /**
     * $index is a task assessment type
     *
     * @param $index
     * @return bool
     */
    public static function isTask($index)
    {
        return $index == self::ASSESSMENT_TYPE_TASK;
    }

    /* Getters/Setters
     */

    /**
     * @return array
     */
    public static function getUrlFragments()
    {
        return self::$urlFragments;
    }
}