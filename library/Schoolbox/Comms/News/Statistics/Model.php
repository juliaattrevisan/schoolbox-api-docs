<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
     * A datetime associated with when this interaction occurred that isn't tied to the
     * mapper behaviours of created_at and updated_at. This allows us to mass produce data
     * and fudge some of the datetimes to simulate scenarios
     *
     * @var \Alaress_Date_Format
     */
    private $occursAt;

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
     * @param null|string $referrer
     * @return $this
     */
    public function setReferrer(?string $referrer): self
    {
        $this->referrer = $referrer;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format
     */
    public function getOccursAt(): \Alaress_Date_Format
    {
        return $this->occursAt;
    }


    /**
     * @param \Alaress_Date_Format $occursAt
     * @return Model
     */
    public function setOccursAt(\Alaress_Date_Format $occursAt): self
    {
        $this->occursAt = $occursAt;
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
