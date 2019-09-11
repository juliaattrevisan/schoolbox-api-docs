<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Model as GradeGroupLevel;
use Schoolbox\Learning\Assessment\IResponse;

class LetterGrade extends AbstractMarkScheme
{
    public const SLUG = 'letter';
    public const PRECISION = 2;

    const TITLE = 'Letter Grade';

    /** @var GradeGroup */
    private $gradeGroup;

    /**
     * This type is comparable numerically, through the levels within this
     * object's grade group.
     *
     * @var bool
     */
    const QUANTITATIVE = true;

    /**
     * @param GradeGroup $gradeGroup
     */
    public function __construct(GradeGroup $gradeGroup)
    {
        $this->setGradeGroup($gradeGroup);
    }

    /**
     * @inheritdoc
     * @return string The caption for the first grade level $normalizedMark is not less than,
     *                or an en-dash if it's not less than none of them.
     * @throws \RangeException If $normalisedMark is outside 0.0 to 100.0
     * @throws \InvalidArgumentException  If $normalisedMark isn't a numeric type
     */
    public function getHumanReadableMark($normalisedMark, IResponse $response = null)
    {
        if (null === $normalisedMark) {
            return null;
        }

        $normalisedMark = $this->filterNormalisedMark($normalisedMark);

        $lowestGradeLevel = null;
        foreach ($this->getGradeGroup()->getGradeLevels() as $gradeLevel) {
            $lowestGradeLevel = $gradeLevel;
            if (round($normalisedMark, $this::PRECISION) >= round($gradeLevel->getLowerBound(), $this::PRECISION)) {
                return $gradeLevel->getCaption();
            }
        }

        // Is this mark lower than all marks? Have whatever the lowest mark was.
        // If somehow this is an empty grade group, have a nully value (in this case, en-dash)
        return ($lowestGradeLevel instanceof GradeGroupLevel)
            ? $lowestGradeLevel->getCaption()
            : '–';
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
        if ($gradeGroup->getGradeLevels()) {
            $gradeGroup->getGradeLevels()->sortBy('lowerBound', 'desc');
        }

        $this->gradeGroup = $gradeGroup;
        return $this;
    }

    /**
     * Add configured grade levels to context.
     *
     * @return array
     */
    public function generateContext(): array
    {
        $context = parent::generateContext();
        $context['conf'] = [];

        foreach ($this->gradeGroup->getGradeLevels() as $gradeLevel) {
            $context['conf'][$gradeLevel->getLowerBound()] = $gradeLevel->getCaption();
        }
        return $context;
    }
}
