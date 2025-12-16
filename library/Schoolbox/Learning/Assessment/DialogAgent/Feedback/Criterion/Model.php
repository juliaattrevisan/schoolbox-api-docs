<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Feedback\Criterion;

use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class Model
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;


    /**
     * @var string|null
     */
    private ?string $body = null;


    /**
     * This is intentionally just an id to skip hydrating a Scenario Criterion instance via the swamp (very laborious)
     * which would have doubled the amount of hydration and instance management throughout this whole workflow.
     *
     * Most of the time you'd never need it: feedback is interfaced by the machine, not the human, so reliance on ids
     * only should (should) be sufficient (for now)
     *
     * (Feedback DataModels, however, already return the info that you'd normally convert into a Domain Representation)
     *
     * One way to avoid all this is to make a shared Data/Domain Object hybrid such that its representation is the same
     * in both usage contexts, but...that's a bit weird given the intended swamp paradigm.
     *
     * @var int|null
     */
    private ?int $criteriaId = null;


    /* Getters/Setters
     */

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
}
