<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Task\Model as Task;

/**
 * Encapsulates the mapping between Assessment Types and their twig-template-directories / URL-fragments
 *
 * This class essentially enumerates the concrete implementations of IAssessable
 * Does not map to DB table as that relationship is represented in join tables
 *
 * Does not belong on IAssessable as interface cannot have behaviour (and shouldn't know about implementors)
 * Does not belong on AbstractAssessment as parent class should not know about children
 */
class AssessmentType
{
    // Template Directories
    const TEMPLATE_DIRECTORY_PROJECT = 'project';
    const TEMPLATE_DIRECTORY_DUE_WORK = 'dueWork';
    const TEMPLATE_DIRECTORY_TASK = 'task';
    const TEMPLATE_DIRECTORY_QUIZ = 'quiz';

    // URL fragments
    // (for now, these are both the same values. Could trivially split into URL- vs template-specific if required)
    const URL_FRAGMENT_PROJECT = self::TEMPLATE_DIRECTORY_PROJECT;
    const URL_FRAGMENT_DUE_WORK = self::TEMPLATE_DIRECTORY_DUE_WORK;
    const URL_FRAGMENT_TASK = self::TEMPLATE_DIRECTORY_TASK;
    const URL_FRAGMENT_QUIZ = self::TEMPLATE_DIRECTORY_QUIZ;


    /**
     * Get twig template directory for a given IAssessable
     *
     * @param IAssessable $assessment
     * @return string
     */
    public static function getTemplateDirectory(IAssessable $assessment)
    {
        if($assessment instanceof Project){
            return self::TEMPLATE_DIRECTORY_PROJECT;
        } elseif($assessment instanceof DueWork){
            return self::TEMPLATE_DIRECTORY_DUE_WORK;
        } elseif($assessment instanceof Task){
            return self::TEMPLATE_DIRECTORY_TASK;
        } elseif($assessment instanceof Quiz){
            return self::TEMPLATE_DIRECTORY_QUIZ;
        }
        throw new \UnexpectedValueException('Unknown IAssessable: '.get_class($assessment));
    }


    /**
     * Get the numeric representation from string representation
     *
     * @param string $urlFragment
     * @return IAssessable
     */
    public static function getAssessmentByUrlFragment($urlFragment)
    {
        if(self::URL_FRAGMENT_PROJECT == $urlFragment){
            return new Project();
        } elseif(self::URL_FRAGMENT_DUE_WORK == $urlFragment){
            return new DueWork();
        } elseif(self::URL_FRAGMENT_TASK == $urlFragment){
            return new Task();
        } elseif(self::URL_FRAGMENT_QUIZ == $urlFragment){
            return new Quiz();
        }
        throw new \UnexpectedValueException("Unknown URL fragment: $urlFragment");
    }


    /* Getters/Setters
     */

    /**
     * Returns the available URL fragments
     * @return array
     */
    public static function getUrlFragments()
    {
        return [
            self::TEMPLATE_DIRECTORY_PROJECT,
            self::TEMPLATE_DIRECTORY_DUE_WORK,
            self::TEMPLATE_DIRECTORY_TASK,
            self::TEMPLATE_DIRECTORY_QUIZ,
        ];
    }
}