<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti\Feedback;

use Schoolbox\Learning\Assessment\AbstractFeedback;
use Schoolbox\Learning\MarkScheme\AbstractMarkScheme;
use Schoolbox\Learning\MarkScheme\IMarkScheme;

/**
 * Domain model for LTI assessment feedback.
 */
class Model extends AbstractFeedback
{
    /**
     * The score which was given to the student, if any.
     */
    private ?float $scoreGiven = null;

    /**
     * The maximum possible score available for the assessment at time of
     * scoring, if provided.
     */
    private ?float $scoreMaximum = null;

    /**
     * @return float|null
     */
    public function getScoreGiven(): ?float
    {
        return $this->scoreGiven;
    }

    /**
     * @param float|null $scoreGiven
     * @return self
     */
    public function setScoreGiven(?float $scoreGiven): self
    {
        $this->scoreGiven = $scoreGiven;
        return $this;
    }

    /**
     * @return float|null
     */
    public function getScoreMaximum(): ?float
    {
        return $this->scoreMaximum;
    }

    /**
     * @param float|null $scoreMaximum
     * @return self
     */
    public function setScoreMaximum(?float $scoreMaximum): self
    {
        $this->scoreMaximum = $scoreMaximum;
        return $this;
    }

    /**
     * Calculate the normalised mark for this feedback, based on whether it
     * has a given score or not.
     *
     * @return float|null
     */
    public function getNormalisedMark()
    {
        if (!$this->hasValidMark()) {
            return null;
        }
        return floatval(IMarkScheme::NORMALISED_MARK_MAX * $this->scoreGiven / $this->scoreMaximum);
    }

    /**
     * @return string
     */
    public function getHumanReadableMark()
    {
        if (!$this->hasValidMark()) {
            return '';
        }

        // Ensure trailing zeroes are trimmed after rounding
        return sprintf(
            '%s / %s',
            round($this->scoreGiven, AbstractMarkScheme::PRECISION) + 0,
            round($this->scoreMaximum, AbstractMarkScheme::PRECISION) + 0
        );
    }

    /**
     * Does this feedback have a valid mark?
     *
     * It does if it has:
     * * a non-null score given
     * * a non-null score maximum > 0
     *
     * This feedback won't have a human-readable or normalised mark if it
     * doesn't have a valid mark.
     *
     * @return bool
     */
    private function hasValidMark(): bool
    {
        if ($this->scoreGiven === null) {
            return false;
        }
        if ($this->scoreMaximum === null || $this->scoreMaximum <= 0) {
            return false;
        }
        return true;
    }
}
