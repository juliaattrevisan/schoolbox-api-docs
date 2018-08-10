<?php
/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Statistics;

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
     * @var int
     */
    private $articleId;

    /**
     * @var int
     */
    private $userId;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $referrer;


    /**
     * @return null|int
     */
    public function getArticleId(): ?int
    {
        return $this->articleId;
    }


    /**
     * @param int $articleId
     * @return $this
     */
    public function setArticleId(int $articleId): self
    {
        $this->articleId = $articleId;
        return $this;
    }


    /**
     * @return null|int
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }


    /**
     * @param int $userId
     * @return $this
     */
    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getType(): ?string
    {
        return $this->type;
    }


    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getReferrer(): ?string
    {
        return $this->referrer;
    }


    /**
     * @param string $referrer
     * @return $this
     */
    public function setReferrer(string $referrer): self
    {
        $this->referrer = $referrer;
        return $this;
    }
}
