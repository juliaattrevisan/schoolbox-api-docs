<?php
/**
 * Mapping
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\MarkScheme;

use \Schoolbox\Learning\Assessment\Types as AssessmentTypes;
use \Schoolbox\Assessment\Component\SubmissionBox\MarkType;

class Mapping
{
    /**
     * Mapping of consts to human-readable titles
     *
     * @var array
     */
    private $map = [];

    /**
     * Mapping constructor.
     */
    public function __construct()
    {
        $this->setMap([
            AssessmentTypes::ASSESSMENT_TYPE_PROJECT  => [
                MarkType::TYPE_PERCENT => Percentage::getTitle(),
                MarkType::TYPE_NONE => None::getTitle(),
                MarkType::TYPE_LETTER => LetterGrade::getTitle()
            ],
            AssessmentTypes::ASSESSMENT_TYPE_DUE_WORK => [
                MarkType::TYPE_PERCENT => Percentage::getTitle(),
                MarkType::TYPE_NONE => None::getTitle(),
                MarkType::TYPE_TOTAL => TotalMarkWholeNumber::getTitle(),
                MarkType::TYPE_LETTER => LetterGrade::getTitle()
            ],
            AssessmentTypes::ASSESSMENT_TYPE_TASK     => [
                MarkType::TYPE_COMPLETION => Completion::getTitle(),
            ],
            AssessmentTypes::ASSESSMENT_TYPE_QUIZ     => [
                MarkType::TYPE_TOTAL => TotalMarkFractional::getTitle(),
            ],
        ]);
    }

    /* Getters/Setters
     */

    /**
     * @return array
     */
    public function getMap()
    {
        return $this->map;
    }

    /**
     * @param array $map
     */
    private function setMap($map)
    {
        $this->map = $map;
    }
}