<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Feedback\Criteria;

use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class Model
{
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;


    /**
     * @var int|null
     */
    private ?int $feedbackId = null;


    /**
     * @var int|null
     */
    private ?int $criteriaId = null;


    /**
     * @var string|null
     */
    private ?string $body = null;


    /* Getters/Setters
     */

    /**
     * @return int|null
     */
    public function getFeedbackId(): ?int
    {
        return $this->feedbackId;
    }


    /**
     * @param int|null $feedbackId
     * @return $this
     */
    public function setFeedbackId(?int $feedbackId): self
    {
        $this->feedbackId = $feedbackId;
        return $this;
    }


    /**
     * @return int|null
     */
    public function getCriteriaId(): ?int
    {
        return $this->criteriaId;
    }


    /**
     * @param int|null $criteriaId
     * @return $this
     */
    public function setCriteriaId(?int $criteriaId): self
    {
        $this->criteriaId = $criteriaId;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getBody(): ?string
    {
        return $this->body;
    }


    /**
     * @param string|null $body
     * @return $this
     */
    public function setBody(?string $body): self
    {
        $this->body = $body;
        return $this;
    }
}
