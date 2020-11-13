<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Award;

use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Badge;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

/**
 * Class Model
 *
 * @package Schoolbox\Learning\Badge\Category
 */
class Model implements ISoftDeletable
{
    use ModelTrait\DeletedAtDate;


    /**
     * Consts corresponding to an award Status
     */
    public const AWARD_STATUS_GOAL = 'goal';
    public const AWARD_STATUS_ACHIEVEMENT = 'achieved';


    /**
     * @var User
     */
    private User $recipient;


    /**
     * @var Badge\Model
     */
    private Badge\Model $badge;


    /**
     * A string denoting whether this Award was a Goal or an Achievement
     *
     * @var string
     */
    private string $status;


    /**
     * @var int
     */
    private int $level;


    /**
     * @var User
     */
    private User $awardedBy;


    /**
     * @var \Alaress_Date_Format
     */
    private \Alaress_Date_Format $awardedAt;


    /**
     * @return User
     */
    public function getRecipient(): User
    {
        return $this->recipient;
    }


    /**
     * @param User $recipient
     * @return Model
     */
    public function setRecipient(User $recipient): self
    {
        $this->recipient = $recipient;
        return $this;
    }


    /**
     * @return Badge\Model
     */
    public function getBadge(): Badge\Model
    {
        return $this->badge;
    }


    /**
     * @param Badge\Model $badge
     * @return Model
     */
    public function setBadge(Badge\Model $badge): self
    {
        $this->badge = $badge;
        return $this;
    }


    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }


    /**
     * @param string $status
     * @return Model
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }


    /**
     * @return int
     */
    public function getLevel(): int
    {
        return $this->level;
    }


    /**
     * @param int $level
     * @return Model
     */
    public function setLevel(int $level): self
    {
        $this->level = $level;
        return $this;
    }


    /**
     * @return User
     */
    public function getAwardedBy(): User
    {
        return $this->awardedBy;
    }

    /**
     * @param User $awardedBy
     * @return Model
     */
    public function setAwardedBy(User $awardedBy): self
    {
        $this->awardedBy = $awardedBy;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format
     */
    public function getAwardedAt(): \Alaress_Date_Format
    {
        return $this->awardedAt;
    }


    /**
     * @param \Alaress_Date_Format $awardedAt
     * @return Model
     */
    public function setAwardedAt(\Alaress_Date_Format $awardedAt): self
    {
        $this->awardedAt = $awardedAt;
        return $this;
    }
}
