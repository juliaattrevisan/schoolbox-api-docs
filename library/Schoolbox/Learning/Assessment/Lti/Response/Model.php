<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti\Response;

use Alaress_Date_Format;
use Schoolbox\Learning\Assessment\AbstractResponse;

/**
 * Domain model for LTI assessment responses.
 */
class Model extends AbstractResponse
{
    /**
     * The submission date of the response.
     *
     * This may vary from the creation date:
     * * the submission date is the time the score was created in the tool
     * * the creation date is the time the corresponding response was created in
     *   Schoolbox
     */
    private Alaress_Date_Format $submittedAt;

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
     * A comment given on the response, if any.
     */
    private ?string $comment = null;

    /**
     * The response's creation date is its submit date.
     */
    public function getSubmittedAt()
    {
        return $this->submittedAt;
    }

    /**
     * @param Alaress_Date_Format $submittedAt
     * @return self
     */
    public function setSubmittedAt(Alaress_Date_Format $submittedAt): self
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }

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
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @param string|null $comment
     * @return self
     */
    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }
}
