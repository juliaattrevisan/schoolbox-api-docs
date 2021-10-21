<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti\Feedback;

use Schoolbox\Learning\Assessment\AbstractFeedback;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;

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
        // TODO set normalized mark in \Schoolbox\Learning\Assessment\Lti\Feedback\MapperProxy::transformDomainObjects
        //      instead, based on a mark scheme created for the score itself.
        // See \Schoolbox\Learning\Assessment\DueWork\Feedback\MapperProxy::transformModels
        if ($this->scoreGiven === null) {
            return null;
        }
        if ($this->scoreMaximum === null || $this->scoreMaximum <= 0) {
            return null;
        }
        return floatval(IMarkScheme::NORMALISED_MARK_MAX * $this->scoreGiven / $this->scoreMaximum);
    }

    /**
     * @return string
     */
    public function getHumanReadableMark()
    {
        // TODO figure out where mark scheme comes from
        $markScheme = new TotalMarkFractional($this->scoreMaximum);
        return $markScheme->getHumanReadableMark($this->getNormalisedMark());
    }
}
