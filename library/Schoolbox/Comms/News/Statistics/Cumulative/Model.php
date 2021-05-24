<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Statistics\Cumulative;

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
     * @var \Alaress_Date_Format
     */
    private $date;

    /**
     * @var int
     */
    private $articleId;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $referrer;

    /**
     * One of the roleTypes specified in RoleMapper consts: staff parent student guest
     *
     * @var string
     */
    private $roleType;

    /**
     * @var int
     */
    private $roleId;

    /**
     * @var int
     */
    private $num;


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
     * @return \Alaress_Date_Format
     */
    public function getDate(): \Alaress_Date_Format
    {
        return $this->date;
    }


    /**
     * @param \Alaress_Date_Format $date
     * @return Model
     */
    public function setDate(\Alaress_Date_Format $date): self
    {
        $this->date = $date;
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
     * @param null|string $type
     * @return $this
     */
    public function setType(?string $type): self
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
     * @param null|string $referrer
     * @return $this
     */
    public function setReferrer(?string $referrer): self
    {
        $this->referrer = $referrer;
        return $this;
    }


    /**
     * @return int
     */
    public function getNum(): int
    {
        return $this->num;
    }


    /**
     * @param int $num
     * @return $this
     */
    public function setNum(int $num): self
    {
        $this->num = $num;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getRoleType(): ?string
    {
        return $this->roleType;
    }


    /**
     * @param null|string $roleType
     * @return $this
     */
    public function setRoleType(?string $roleType): self
    {
        $this->roleType = $roleType;
        return $this;
    }


    /**
     * @return null|int
     */
    public function getRoleId(): ?int
    {
        return $this->roleId;
    }


    /**
     * @param null|int $roleId
     * @return Model
     */
    public function setRoleId(?int $roleId): self
    {
        $this->roleId = $roleId;
        return $this;
    }
}
