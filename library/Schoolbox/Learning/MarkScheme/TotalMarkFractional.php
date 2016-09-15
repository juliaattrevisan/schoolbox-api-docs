<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use InvalidArgumentException;
use RangeException;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizFeedback;

/**
 * Permits fractional TotalMark suitable for Quiz.
 *
 * TotalMark CAN be mutated after construction. This is because
 * this class is intended for Quiz, where the $totalMark may vary depending on which questions
 * make up a QuizResponse
 *
 * On instantiation, the $totalMark passed to constructor is expected to be the sum
 * of marks available in all sections of the quiz.
 *
 * Before use, a caller must check if a response $totalMarks may differ, and if so, call setTotalMark()
 * The value passed in must be less than or equal to the original value
 *
 * eg, 'X / 3.75'
 *
 * @method float getTotalMark()
 */
class TotalMarkFractional extends TotalMarkAbstract
{
    const GRANULARITY_NUMERATOR   = 0.25;
    const GRANULARITY_DENOMINATOR = 0.25;

    const TOTAL_MARK_MIN = 0.0;

    /** @var float */
    private $originalTotalMark;

    /**
     * @param int|float $totalMark
     */
    public function __construct($totalMark)
    {
        $this->setOriginalTotalMark($totalMark);
        parent::__construct($totalMark);
    }


    /**
     * @param float $totalMark
     * @throws RangeException if $totalMark is less than permitted minimum
     * @throws RangeException if $totalMark is greater than original total mark
     */
    protected function validateTotalMark($totalMark)
    {
        $totalMarkMin = $this->getTotalMarkMin();
        if($totalMark < $totalMarkMin){
            throw new RangeException("Total mark must be greater than or equal to $totalMarkMin: $totalMark");
        }

        $originalTotalMark = $this->getOriginalTotalMark();
        if($totalMark > $originalTotalMark){
            throw new RangeException("Total mark cannot exceed original total mark. Original: $originalTotalMark, Total: $totalMark");
        }
    }

    /**
     * Returns a string representation of a normalised Mark
     *
     * Granularity of numerator & denominator depends on sub-class
     *
     * @param float          $normalisedMark
     * @param IResponse|null $response
     * @throws InvalidArgumentException if normalised mark is outside valid range
     * @return string
     */
    public function getHumanReadableMark($normalisedMark, IResponse $response = null)
    {
        // Special Case for Quiz where total marks available can vary per Attempt
        if ($response instanceof QuizAttempt) {
            $availableMarks = 0.0;
            /** @var QuizFeedback[] $answers */
            $answers = $response->getAllFeedback();
            foreach($answers as $answer) {
                $availableMarks += $answer->getQuestion()->getMarks();
            }
            $this->setTotalMark($availableMarks);

        }
        return parent::getHumanReadableMark($normalisedMark, $response);
    }


    /* Getters / Setters
     */

    /**
     * @return float
     */
    protected function getOriginalTotalMark()
    {
        return $this->originalTotalMark;
    }

    /**
     * @param float $totalMark
     * @return static
     */
    protected function setOriginalTotalMark($totalMark)
    {
        $this->originalTotalMark = $totalMark;
        return $this;
    }

    /**
     * May have fractional part
     *
     * @param float $totalMark
     * @return static
     */
    protected function setTotalMark($totalMark)
    {
        $this->validateTotalMark($totalMark);
        parent::setTotalMark($totalMark);
        return $this;
    }

    /**
     * @return float
     */
    protected function getGranularityNumerator()
    {
        return self::GRANULARITY_NUMERATOR;
    }

    /**
     * @return float
     */
    protected function getGranularityDenominator()
    {
        return self::GRANULARITY_DENOMINATOR;
    }

    /**
     * @return float
     */
    protected function getTotalMarkMin()
    {
        return self::TOTAL_MARK_MIN;
    }
}