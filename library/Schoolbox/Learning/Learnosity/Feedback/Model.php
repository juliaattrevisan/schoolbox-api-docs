<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Feedback;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;


    /**
     * The attempt to which this learnosity response belongs
     *
     * @var \Schoolbox\Learning\Quiz\Attempt\Model
     */
    private \Schoolbox\Learning\Quiz\Attempt\Model $attempt;


    /* TODO optionally store each question's score here,
     *  at the risk of desyncing with learnosity
     *
     * private ?int $score = null;
     */


    /**
     * The feedback for a particular learnosity question's response
     *
     * @var string|null
     */
    private ?string $comment = null;


    /**
     * The learnosity identifier of a question to which this feedback belongs
     *
     * @var string|null
     */
    private ?string $learnosityReference = null;


    /**
     * @return \Schoolbox\Learning\Quiz\Attempt\Model
     */
    public function getAttempt(): \Schoolbox\Learning\Quiz\Attempt\Model
    {
        return $this->attempt;
    }


    /**
     * @param \Schoolbox\Learning\Quiz\Attempt\Model $attempt
     * @return Model
     */
    public function setAttempt(\Schoolbox\Learning\Quiz\Attempt\Model $attempt): self
    {
        $this->attempt = $attempt;
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
     * @return Model
     */
    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getLearnosityReference(): ?string
    {
        return $this->learnosityReference;
    }


    /**
     * @param string|null $learnosityReference
     * @return Model
     */
    public function setLearnosityReference(?string $learnosityReference): self
    {
        $this->learnosityReference = $learnosityReference;
        return $this;
    }
}
