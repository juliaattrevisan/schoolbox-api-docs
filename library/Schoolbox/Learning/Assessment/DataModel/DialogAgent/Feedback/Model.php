<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback;

use Schoolbox\GenericCollection;
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
     * @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model|null
     */
    private ?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model $dialogAgent = null;


    /**
     * @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Model|null
     */
    private ?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Model $response = null;


    /**
     * @var \Schoolbox\User|null
     */
    private ?\Schoolbox\User $learner = null;


    /**
     * @var GenericCollection|null
     */
    private ?GenericCollection $criteria = null;


    /* Getters/Setters
     */

    /**
     * @return \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model|null
     */
    public function getDialogAgent(): ?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model
    {
        return $this->dialogAgent;
    }


    /**
     * @param \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model|null $dialogAgent
     * @return $this
     */
    public function setDialogAgent(?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model $dialogAgent): self
    {
        $this->dialogAgent = $dialogAgent;
        return $this;
    }


    /**
     * @return \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Model|null
     */
    public function getResponse(): ?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Model
    {
        return $this->response;
    }


    /**
     * @param \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Model|null $response
     * @return $this
     */
    public function setResponse(?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Model $response): self
    {
        $this->response = $response;
        return $this;
    }


    /**
     * @return \Schoolbox\User|null
     */
    public function getLearner(): ?\Schoolbox\User
    {
        return $this->learner;
    }


    /**
     * @param \Schoolbox\User|null $learner
     * @return $this
     */
    public function setLearner(?\Schoolbox\User $learner): self
    {
        $this->learner = $learner;
        return $this;
    }


    /**
     * @return GenericCollection|null
     */
    public function getCriteria(): ?GenericCollection
    {
        return $this->criteria;
    }


    /**
     * @param GenericCollection|null $criteria
     * @return $this
     */
    public function setCriteria(?GenericCollection $criteria): self
    {
        $this->criteria = $criteria;
        return $this;
    }
}
