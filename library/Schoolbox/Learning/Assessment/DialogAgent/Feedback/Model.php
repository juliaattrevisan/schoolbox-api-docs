<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Feedback;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\AbstractFeedback;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class Model extends AbstractFeedback
{
    use CreatedAtDate;
    use UpdatedAtDate;


    /**
     * @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model|null
     */
    private ?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model $dialogAgent = null;


    /**
     * A set of criteria fields that each have feedback content, all collated here to be treated as one big unit
     * \Schoolbox\Learning\Assessment\DialogAgent\Feedback\Criteria[]
     *
     * phpstorm complains about putting that into the actual typedef.
     *
     * @var GenericCollection
     */
    private GenericCollection $criteria;


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
     * @return GenericCollection
     */
    public function getCriteria(): GenericCollection
    {
        return $this->criteria;
    }


    /**
     * @param GenericCollection $criteria
     * @return $this
     */
    public function setCriteria(GenericCollection $criteria): self
    {
        $this->criteria = $criteria;
        return $this;
    }
}
