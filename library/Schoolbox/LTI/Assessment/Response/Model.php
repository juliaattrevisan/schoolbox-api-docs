<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Assessment\Response;

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
}
