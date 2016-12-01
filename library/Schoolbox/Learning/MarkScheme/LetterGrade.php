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
     * @throws DomainException if unable to locate corresponding GradeLevel
     * @return string
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

        throw new DomainException("Unable to determine Human-Readable mark. Normalised: $normalisedMark");
    }

    /**
     * Converts Caption to float.
     *
     * @param string         $humanReadableMark eg 'Excellent'
     * @param IResponse|null $response
     * @throws DomainException if unable to locate corresponding GradeLevel
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
        throw new DomainException("No GradeLevel found. GradeGroup: $gradeGroup, GradeLevel: '.$humanReadableMark");
    }

    /* Getters / Setters
     */

    /**
     * @return GradeGroup
     */
    public function getGradeGroup()
    {
        /* This should never really be the case, but if you try
         * to operate on nonexistent gradelevels, die magnificently
         */
        if (!$this->gradeGroup->getGradeLevels()->count()) {
            throw new \UnderflowException('No grade levels found');
        }

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