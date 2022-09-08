<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Award;

use Alaress_Date_Format;
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
    use ModelTrait\Id;
    use ModelTrait\DeletedAtDate;


    /**
     * Consts corresponding to an award Status
     */
    public const AWARD_STATUS_GOAL = 'goal';
    public const AWARD_STATUS_ACHIEVEMENT = 'achievement';


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
     * @var string|null
     */
    private ?string $status = null;


    /**
     * The reason a badge was awarded to someone
     *
     * @var string|null
     */
    private ?string $reason = null;


    /**
     * @var User
     */
    private User $awardedBy;


    /**
     * @var Alaress_Date_Format|null
     */
    private ?Alaress_Date_Format $awardedAt = null;


    /**
     * When does this achievement/goal expire?
     */
    private ?Alaress_Date_Format $expiresAt = null;


    /**
     * When an user viewed this achievement/goal?
     */
    private ?Alaress_Date_Format $viewedOn = null;


    /**
     * A corresponding Award instance. This correlates a specific usage of a Goal
     * and its corresponding Achievement, if any
     *
     * @var Model|null
     */
    private ?Model $award = null;


    /**
     * Decide whether this awarding was a Goal
     *
     * @return bool
     */
    public function isGoal(): bool
    {
        return self::AWARD_STATUS_GOAL === $this->getStatus();
    }


    /**
     * Decide whether this awarding was an Achievement
     *
     * @return bool
     */
    public function isAchievement(): bool
    {
        return self::AWARD_STATUS_ACHIEVEMENT === $this->getStatus();
    }


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
     * @return string|null
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }


    /**
     * @param string|null $reason
     * @return Model
     */
    public function setReason(?string $reason): self
    {
        $this->reason = $reason;
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
     * @return Alaress_Date_Format
     */
    public function getAwardedAt(): Alaress_Date_Format
    {
        return $this->awardedAt;
    }


    /**
     * @param Alaress_Date_Format $awardedAt
     * @return Model
     */
    public function setAwardedAt(Alaress_Date_Format $awardedAt): self
    {
        $this->awardedAt = $awardedAt;
        return $this;
    }


    /**
     * @return Alaress_Date_Format|null
     */
    public function getExpiresAt(): ?Alaress_Date_Format
    {
        return $this->expiresAt;
    }


    /**
     * @param Alaress_Date_Format|null $expiresAt
     * @return $this
     */
    public function setExpiresAt(?Alaress_Date_Format $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }


    /**
     * @return Alaress_Date_Format|null
     */
    public function getViewedOn(): ?Alaress_Date_Format
    {
        return $this->viewedOn;
    }


    /**
     * @param Alaress_Date_Format|null $viewedOn
     * @return $this
     */
    public function setViewedOn(?Alaress_Date_Format $viewedOn): self
    {
        $this->viewedOn = $viewedOn;
        return $this;
    }


    /**
     * @return Model|null
     */
    public function getAward(): ?Model
    {
        return $this->award;
    }


    /**
     * @param Model|null $award
     * @return $this
     */
    public function setAward(?Model $award): self
    {
        $this->award = $award;
        return $this;
    }

    /**
     * Generates index for JSON data
     * @return string
     */
    public function getBadgeIndex(): string
    {
        return "badge-{$this->getStatus()}-{$this->getBadge()->getId()}";
    }
}
