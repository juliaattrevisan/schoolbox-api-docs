<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use InvalidArgumentException;
use DomainException;

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;

class LetterGrade extends AbstractMarkScheme
{
    /** @var GradeGroup */
    private $gradeGroup;

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
     * @param float $normalisedMark
     * @throws InvalidArgumentException if normalised mark is outside valid range
     * @throws DomainException if unable to locate corresponding GradeLevel
     * @return string
     */
    public function getHumanReadableMark($normalisedMark)
    {
        $this->validateNormalisedMark($normalisedMark);

        foreach ($this->getGradeGroup()->getGradeLevels() as $gradeLevel) {
            if ($normalisedMark >= $gradeLevel->getLowerBound()) {
                return $gradeLevel->getCaption();
            }
        }

        throw new DomainException("Unable to determine Human-Readable mark. Normalised: $normalisedMark");
    }

    /**
     * Converts Caption to float.
     *
     * @param string $humanReadableMark eg 'Excellent'
     * @throws DomainException if unable to locate corresponding GradeLevel
     * @return float
     */
    public function getNormalisedMark($humanReadableMark)
    {
        foreach ($this->getGradeGroup()->getGradeLevels() as $gradeLevel) {
            if ($gradeLevel->getCaption() == $humanReadableMark) {
                return floatval($gradeLevel->getRawGrade());
            }
        }

        $gradeGroup = $this->getGradeGroup()->getName();
        throw new DomainException("No GradeLevel found. GradeGroup: $gradeGroup, GradeLevel: '.$humanReadableMark");
    }

    /* Getters / Setters
     */

    /**
     * @return GradeGroup
     */
    protected function getGradeGroup()
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