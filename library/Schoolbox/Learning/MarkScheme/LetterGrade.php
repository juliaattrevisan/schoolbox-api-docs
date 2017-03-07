<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Learning\Assessment\IResponse;

class LetterGrade extends AbstractMarkScheme
{
    const TITLE = 'Letter Grade';

    /** @var GradeGroup */
    private $gradeGroup;

    /**
     * This type is not comparable numerically.
     *
     * @var bool
     */
    protected $quantitative = false;

    /**
     * @param GradeGroup $gradeGroup
     */
    public function __construct(GradeGroup $gradeGroup)
    {
        $this->setGradeGroup($gradeGroup);
    }

    /**
     * Returns a string representation of a normalised Mark
     *
     * @param float          $normalisedMark
     * @param IResponse|null $response
     * @throws InvalidArgumentException if normalised mark is outside valid range
     * @return string The caption for the first grade level $normalizedMark is not less than,
     *                or an en-dash if it's not less than none of them.
     */
    public function getHumanReadableMark($normalisedMark, IResponse $response = null)
    {
        if(is_null($normalisedMark)){
            return null;
        }

        $this->validateNormalisedMark($normalisedMark);

        foreach ($this->getGradeGroup()->getGradeLevels() as $gradeLevel) {
            if ($normalisedMark >= $gradeLevel->getLowerBound()) {
                return $gradeLevel->getCaption();
            }
        }

        // Is this mark lower than all marks? Have a nully value (in this case, en-dash)
        return '–';
    }

    /**
     * Converts Caption to float.
     *
     * @param string         $humanReadableMark eg 'Excellent'
     * @param IResponse|null $response
     * @throws InvalidArgumentException if unable to locate corresponding GradeLevel
     * @return float
     */
    public function getNormalisedMark($humanReadableMark, IResponse $response = null)
    {
        foreach ($this->getGradeGroup()->getGradeLevels() as $gradeLevel) {
            if ($gradeLevel->getCaption() == $humanReadableMark) {
                return floatval($gradeLevel->getRawGrade());
            }
        }

        $gradeGroup = $this->getGradeGroup()->getName();
        throw new InvalidArgumentException("No GradeLevel found. GradeGroup: $gradeGroup, GradeLevel: '.$humanReadableMark");
    }

    /* Getters / Setters
     */

    /**
     * @return GradeGroup
     */
    public function getGradeGroup()
    {
        return $this->gradeGroup;
    }

    /**
     * @param GradeGroup $gradeGroup
     * @return static
     */
    protected function setGradeGroup(GradeGroup $gradeGroup)
    {
        // Ensure levels are sorted DESC so facilitate search traversal
        $gradeGroup->getGradeLevels()->sortBy('lowerBound', 'desc');

        $this->gradeGroup = $gradeGroup;
        return $this;
    }
}
