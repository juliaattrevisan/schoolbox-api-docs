<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback;

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
     * @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Model|null
     */
    private ?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Model $response = null;


    /**
     * @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Criteria\Model|null
     */
    private ?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Criteria\Model $criteria = null;


    /**
     * @var string|null
     */
    private ?string $body = null;


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
     * @return \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Criteria\Model|null
     */
    public function getCriteria(): ?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Criteria\Model
    {
        return $this->criteria;
    }


    /**
     * @param \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Criteria\Model|null $criteria
     * @return $this
     */
    public function setCriteria(?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Criteria\Model $criteria): self
    {
        $this->criteria = $criteria;
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
