<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent\Feedback;

use Schoolbox\Mapper\Traits\Model\Id;

class Criteria
{
    use Id;


    /**
     * The id of the scenario criterion to which this feedback row is related
     *
     * @var int|null
     */
    private ?int $criteriaId = null;


    /**
     * The actual text of the feedback corresponding to the scenario criterion
     *
     * @var string|null
     */
    private ?string $body = null;


    /* Getters/Setters
     */

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
