<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Criterion;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;


    /**
     * @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Model|null
     */
    private ?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Model $feedback = null;


    /**
     * @var \Schoolbox\Admin\DialogAgent\Scenario\Criterion\Model|null
     */
    private ?\Schoolbox\Admin\DialogAgent\Scenario\Criterion\Model $scenarioCriterion = null;


    /**
     * @var string|null
     */
    private ?string $body = null;


    /* Getters/Setters
     */

    /**
     * @return \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Model|null
     */
    public function getFeedback(): ?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Model
    {
        return $this->feedback;
    }


    /**
     * @param \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Model|null $feedback
     * @return $this
     */
    public function setFeedback(?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Model $feedback): self
    {
        $this->feedback = $feedback;
        return $this;
    }


    /**
     * @return \Schoolbox\Admin\DialogAgent\Scenario\Criterion\Model|null
     */
    public function getScenarioCriterion(): ?\Schoolbox\Admin\DialogAgent\Scenario\Criterion\Model
    {
        return $this->scenarioCriterion;
    }


    /**
     * @param \Schoolbox\Admin\DialogAgent\Scenario\Criterion\Model|null $scenarioCriterion
     * @return $this
     */
    public function setScenarioCriterion(?\Schoolbox\Admin\DialogAgent\Scenario\Criterion\Model $scenarioCriterion): self
    {
        $this->scenarioCriterion = $scenarioCriterion;
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
